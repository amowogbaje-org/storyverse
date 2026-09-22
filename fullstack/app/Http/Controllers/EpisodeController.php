<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\Story;
use App\Services\StoryAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EpisodeController extends Controller
{
    private const PREVIEW_CACHE_TTL_MINUTES = 30;

    public function __construct(private StoryAccessService $access) {}

    public function show(Request $request, string $slug, int $episodeNumber)
    {
        $user = $request->user();

        // Episodes 1-guestLimit() are the platform-wide free preview - the
        // same bytes go out to every reader, so they're the safest, highest-
        // traffic content on the site to cache.
        $cacheable = $episodeNumber <= $this->access->guestLimit();

        $payload = $cacheable
            ? Cache::remember(
                $this->previewCacheKey($slug, $episodeNumber),
                now()->addMinutes(self::PREVIEW_CACHE_TTL_MINUTES),
                fn () => $this->loadEpisodePayload($slug, $episodeNumber)
            )
            : $this->loadEpisodePayload($slug, $episodeNumber);

        abort_if(! $payload, 404);

        $story = Story::make($payload['story']);
        $story->id = $payload['story']['id'];
        $episode = Episode::make($payload['episode']);
        $episode->id = $payload['episode']['id'];

        $limit = $this->access->accessibleEpisodeLimit($story, $user);
        $lockReason = $this->access->lockReasonForLimit($limit, $episode, $user);

        if ($lockReason !== null) {
            return view('episodes.locked', [
                'story' => $story,
                'episode' => $episode,
                'reason' => $lockReason, // 'guest_limit' -> prompt to register, 'premium_required' -> prompt to buy
            ]);
        }

        $progress = $user
            ? DB::table('reading_progress')->where('user_id', $user->id)->where('episode_id', $episode->id)->value('progress_percent')
            : null;

        // Total published episode count, for prev/next nav - cheap single query.
        $episodeNumbers = DB::table('episodes')->where('story_id', $story->id)->where('status', 'published')
            ->orderBy('episode_number')->pluck('episode_number');

        return view('episodes.show', [
            'story' => $story,
            'episode' => $episode,
            'progressPercent' => $progress,
            'prevNumber' => $episodeNumbers->filter(fn ($n) => $n < $episodeNumber)->last(),
            'nextNumber' => $episodeNumbers->filter(fn ($n) => $n > $episodeNumber)->first(),
        ]);
    }

    /** @return array{story: array, episode: array}|null */
    private function loadEpisodePayload(string $slug, int $episodeNumber): ?array
    {
        $story = Story::where('slug', $slug)->where('status', 'published')->first();

        if (! $story) {
            return null;
        }

        $episode = $story->publishedEpisodes()->where('episode_number', $episodeNumber)->first();

        if (! $episode) {
            return null;
        }

        return [
            'story' => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
                'access_type' => $story->access_type,
            ],
            'episode' => [
                'id' => $episode->id,
                'title' => $episode->title,
                'episode_number' => $episode->episode_number,
                'content' => $episode->content,
                'word_count' => $episode->word_count,
            ],
        ];
    }

    private function previewCacheKey(string $slug, int $episodeNumber): string
    {
        return "episode-preview:{$slug}:{$episodeNumber}";
    }

    /** Called whenever an episode's content/number/publish state changes, once the author studio ships here. */
    public static function forgetPreviewCache(string $slug, int $episodeNumber): void
    {
        Cache::forget("episode-preview:{$slug}:{$episodeNumber}");
    }
}

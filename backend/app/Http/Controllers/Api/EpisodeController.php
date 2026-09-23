<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\RecordsStoryViews;
use App\Models\Episode;
use App\Models\Story;
use App\Services\StoryAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EpisodeController extends Controller
{
    use RecordsStoryViews;

    private const PREVIEW_CACHE_TTL_MINUTES = 30;

    public function __construct(private StoryAccessService $access) {}

    public function show(Request $request, string $slug, int $episodeNumber)
    {
        $user = $this->currentUser($request);

        // Episodes 1-2 (StoryAccessService::guestLimit(), configurable) are the
        // platform-wide
        // free preview: every guest and every signed-in reader can always read
        // them, on every story, regardless of subscription state or monetization
        // thresholds - the same bytes go out to every single reader. That makes
        // them the highest-traffic, most-repeated, and safest content on the
        // whole site to cache. On a hit this skips the story/episode DB lookup
        // entirely. Uses only the plain Cache facade (no redis-only features
        // like tags), so this works unchanged whether CACHE_STORE is redis
        // (Docker/cloud) or file/database (cPanel, where redis isn't available).
        $cacheable = $episodeNumber <= $this->access->guestLimit();

        $payload = $cacheable
            ? Cache::remember(
                $this->previewCacheKey($slug, $episodeNumber),
                now()->addMinutes(self::PREVIEW_CACHE_TTL_MINUTES),
                fn () => $this->loadEpisodePayload($slug, $episodeNumber)
            )
            : $this->loadEpisodePayload($slug, $episodeNumber);

        if (! $payload) {
            abort(404);
        }

        // Rehydrated without hitting the DB (Model::make() just sets attributes) -
        // the access-check methods only ever read plain attributes/ids off these,
        // never relations, so this is safe even on a cache hit. Still runs the
        // real check rather than assuming episodes 1-2 pass: if guestLimit() is
        // ever lowered, this keeps blocking correctly instead of silently
        // trusting a now-stale assumption.
        $story = Story::make($payload['story']);
        $story->id = $payload['story']['id'];
        $episode = Episode::make($payload['episode']);
        $episode->id = $payload['episode']['id'];

        // Counts as a story view the same way visiting the story detail page
        // does (they used to be two separate requests hitting two separate
        // controllers that each recorded a view; now it's one request, one
        // recording). Deliberately BEFORE the lock check below, matching the
        // old behavior: opening a locked episode's URL directly still counted
        // as having viewed the story, only the episode content itself 403s.
        $this->recordStoryView($request, $story, $user);

        // See InteractionController::updateProgress for why this is computed
        // once and reused, rather than calling canAccessEpisode() then
        // lockReason() separately (each re-runs the same real queries).
        $limit = $this->access->accessibleEpisodeLimit($story, $user);
        $lockReason = $this->access->lockReasonForLimit($limit, $episode, $user);

        if ($lockReason !== null) {
            // `story` is still included here (title/description/cover/
            // access_type/episode numbers) even though the episode itself is
            // locked - EpisodeReaderPage's client-side gate (utils/access.js)
            // decides whether to show the reader or the upsell banner purely
            // from `story`, without waiting on episode content to resolve.
            // Before the story fetch was folded into this endpoint, that data
            // came from an independent, always-successful GET /stories/{slug}
            // call regardless of this episode's own lock state - omitting it
            // here would leave the reader page stuck on its loading spinner
            // for any locked episode.
            return $this->error($lockReason, 'This episode is locked.', 403, ['story' => $payload['story']]);
        }

        if ($user) {
            \App\Models\UserActivityEvent::create([
                'user_id' => $user->id,
                'event_type' => 'episode_opened',
                'metadata' => ['episode_id' => $episode->id, 'story_id' => $story->id],
                'created_at' => now(),
            ]);
            \App\Events\UserActivityLogged::dispatch($user->id, 'episode_opened', ['episode_id' => $episode->id, 'story_id' => $story->id]);
        }

        $progress = $user
            ? DB::table('reading_progress')->where('user_id', $user->id)->where('episode_id', $episode->id)->value('progress_percent')
            : null;

        return $this->ok([
            'id' => $episode->id,
            'title' => $payload['episode']['title'],
            'episode_number' => $payload['episode']['episode_number'],
            'content' => $payload['episode']['content'],
            'word_count' => $payload['episode']['word_count'],
            'progress_percent' => $progress,
            // Replaces the reader page's separate GET /stories/{slug} call.
            // Only what EpisodeReaderPage actually reads off `story` -
            // title/description/cover_image_url for the header + SEO tags,
            // access_type (client-side gating math in utils/access.js), and
            // episodes[].episode_number for prev/next nav. None of this is
            // per-user, so it lives in the same cached payload as the episode
            // itself (see loadEpisodePayload) rather than being computed
            // fresh on every request.
            'story' => $payload['story'],
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
                'description' => $story->description,
                'cover_image_url' => $story->cover_image_url,
                'access_type' => $story->access_type,
                // Numbers only, in order - StoryDetailPage's separate
                // GET /stories/{slug} still returns the full per-episode
                // locked/lock_reason/reader_progress_percent breakdown for
                // its own episode list UI; this page only ever needed the
                // numbers for prev/next links.
                'episodes' => $story->publishedEpisodes()
                    ->orderBy('episode_number')
                    ->pluck('episode_number')
                    ->map(fn ($n) => ['episode_number' => $n])
                    ->values(),
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

    /**
     * Called from Admin\EpisodeManagementController whenever an episode's
     * content, number, or publish state changes - keeps the preview cache from
     * serving stale text after an edit. Story slug changes aren't handled here
     * (rare, and go through StoryManagementController) - see that controller's
     * own cache-busting for the homepage lists.
     */
    public static function forgetPreviewCache(string $slug, int $episodeNumber): void
    {
        Cache::forget("episode-preview:{$slug}:{$episodeNumber}");
    }

    /**
     * Publishing or deleting an episode changes the *set* of episode numbers
     * for the story, which is embedded in every cached guest-preview payload
     * (see loadEpisodePayload's 'episodes' field, added when the reader
     * page's separate story call was folded into this one). A plain
     * forgetPreviewCache($slug, $editedEpisodeNumber) only busts that one
     * episode's own cache entry, not episodes 1..guestLimit()'s - which is
     * what a reader sitting on episode 1 actually has cached. Call this too
     * (in addition to forgetPreviewCache) whenever the episode list changes,
     * not just its content.
     */
    public static function forgetGuestPreviewCaches(string $slug): void
    {
        $limit = app(StoryAccessService::class)->guestLimit();

        for ($n = 1; $n <= $limit; $n++) {
            Cache::forget("episode-preview:{$slug}:{$n}");
        }
    }
}

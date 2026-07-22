<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReadingProgress;
use App\Models\Story;
use App\Models\StoryView;
use App\Services\StoryAccessService;
use App\Support\StoryCardPresenter;
use Illuminate\Http\Request;

class StoryController extends Controller
{
    public function __construct(private StoryAccessService $access) {}

    public function index(Request $request)
    {
        $query = Story::query()
            ->where('status', 'published')
            ->with(['penName', 'category', 'genres']);

        if ($category = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        if ($genre = $request->query('genre')) {
            $query->whereHas('genres', fn ($q) => $q->where('slug', $genre));
        }

        if ($penName = $request->query('pen_name')) {
            $query->whereHas('penName', fn ($q) => $q->where('slug', $penName));
        }

        if ($accessType = $request->query('access_type')) {
            $query->where('access_type', $accessType);
        }

        match ($request->query('sort', 'new')) {
            'popular' => $query->orderByDesc('views_count'),
            default => $query->orderByDesc('published_at'),
        };

        $user = $this->currentUser($request);
        $paginator = $query->cursorPaginate(20);
        $progress = StoryCardPresenter::progressMap($user, collect($paginator->items())->pluck('id'));

        return $this->paginated($paginator, fn (Story $story) => StoryCardPresenter::card($story, $user, $progress));
    }

    public function newReleases(Request $request)
    {
        $stories = Story::where('status', 'published')
            ->orderByDesc('published_at')
            ->with(['penName', 'category'])
            ->limit(10)
            ->get();

        $user = $this->currentUser($request);
        $progress = StoryCardPresenter::progressMap($user, $stories->pluck('id'));

        return $this->ok($stories->map(fn (Story $s) => StoryCardPresenter::card($s, $user, $progress)));
    }

    public function popular(Request $request)
    {
        $stories = Story::where('status', 'published')
            ->orderByDesc('views_count')
            ->with(['penName', 'category'])
            ->limit(10)
            ->get();

        $user = $this->currentUser($request);
        $progress = StoryCardPresenter::progressMap($user, $stories->pluck('id'));

        return $this->ok($stories->map(fn (Story $s) => StoryCardPresenter::card($s, $user, $progress)));
    }

    public function show(Request $request, string $slug)
    {
        $story = Story::where('slug', $slug)
            ->where('status', 'published')
            ->with(['penName', 'category', 'genres', 'publishedEpisodes'])
            ->firstOrFail();

        $user = $this->currentUser($request);

        // Analytics: every story-detail hit is a "read" of the story page itself.
        // Kept as a raw insert + increment (not the heavier UserActivityEvent pipeline)
        // since this fires on every guest pageview too.
        StoryView::create([
            'user_id' => $user?->id,
            'story_id' => $story->id,
            'session_hash' => $this->sessionHash($request),
            'viewed_at' => now(),
        ]);
        $story->increment('views_count');

        $limit = $this->access->accessibleEpisodeLimit($story, $user);

        $episodeProgress = $user
            ? ReadingProgress::where('user_id', $user->id)
                ->where('story_id', $story->id)
                ->pluck('progress_percent', 'episode_id')
            : collect();

        $episodes = $story->publishedEpisodes->map(function ($episode) use ($story, $user, $limit, $episodeProgress) {
            $locked = $limit !== null && $episode->episode_number > $limit;

            return [
                'id' => $episode->id,
                'title' => $episode->title,
                'episode_number' => $episode->episode_number,
                'locked' => $locked,
                'lock_reason' => $this->access->lockReason($story, $episode, $user),
                'reader_progress_percent' => $episodeProgress[$episode->id] ?? null,
            ];
        });

        $progress = StoryCardPresenter::progressMap($user, collect([$story->id]));

        return $this->ok([
            ...StoryCardPresenter::card($story, $user, $progress),
            'genres' => $story->genres->map(fn ($g) => ['slug' => $g->slug, 'name' => $g->name]),
            'episodes' => $episodes,
        ]);
    }
}

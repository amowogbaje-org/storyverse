<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReadingProgress;
use App\Models\Story;
use App\Models\StoryView;
use App\Services\StoryAccessService;
use App\Support\HomeCache;
use App\Support\StoryCardPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StoryController extends Controller
{
    public function __construct(private StoryAccessService $access) {}

    public function index(Request $request)
    {
        $query = Story::query()
            ->where('status', 'published')
            ->with(['penName', 'categories', 'genres']);

        if ($category = $request->query('category')) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $category));
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
        $ids = collect($paginator->items())->pluck('id');
        $progress = StoryCardPresenter::progressMap($user, $ids);
        $liked = StoryCardPresenter::likedMap($user, $ids);
        $bookmarked = StoryCardPresenter::bookmarkedMap($user, $ids);

        return $this->paginated($paginator, fn (Story $story) => StoryCardPresenter::card($story, $user, $progress, $liked, $bookmarked));
    }

    public function newReleases(Request $request)
    {
        // The story list itself (titles, covers, counts as of the last cache
        // refresh) is identical for every visitor, so it's the part worth
        // caching for a faster homepage. See HomeCache's docblock for why the
        // per-user like/bookmark/progress overlay is deliberately kept outside
        // the cached payload and applied fresh below.
        $stories = HomeCache::remember(HomeCache::NEW_RELEASES_KEY, fn () => Story::where('status', 'published')
            ->orderByDesc('published_at')
            ->with(['penName', 'categories'])
            ->limit(10)
            ->get());

        $user = $this->currentUser($request);
        $ids = $stories->pluck('id');
        $progress = StoryCardPresenter::progressMap($user, $ids);
        $liked = StoryCardPresenter::likedMap($user, $ids);
        $bookmarked = StoryCardPresenter::bookmarkedMap($user, $ids);

        return $this->ok($stories->map(fn (Story $s) => StoryCardPresenter::card($s, $user, $progress, $liked, $bookmarked)));
    }

    public function popular(Request $request)
    {
        $stories = HomeCache::remember(HomeCache::POPULAR_KEY, fn () => Story::where('status', 'published')
            ->orderByDesc('views_count')
            ->with(['penName', 'categories'])
            ->limit(10)
            ->get());

        $user = $this->currentUser($request);
        $ids = $stories->pluck('id');
        $progress = StoryCardPresenter::progressMap($user, $ids);
        $liked = StoryCardPresenter::likedMap($user, $ids);
        $bookmarked = StoryCardPresenter::bookmarkedMap($user, $ids);

        return $this->ok($stories->map(fn (Story $s) => StoryCardPresenter::card($s, $user, $progress, $liked, $bookmarked)));
    }

    public function show(Request $request, string $slug)
    {
        $story = Story::where('slug', $slug)
            ->where('status', 'published')
            ->with(['penName', 'categories', 'genres', 'publishedEpisodes', 'prices'])
            ->firstOrFail();

        $user = $this->currentUser($request);

        // A view only counts once per visitor per hour, not on every reload/
        // re-render - see shouldCountView() below for why and how.
        if ($this->shouldCountView($request, $story, $user)) {
            StoryView::create([
                'user_id' => $user?->id,
                'story_id' => $story->id,
                'session_hash' => $this->sessionHash($request),
                'viewed_at' => now(),
            ]);
            $story->increment('views_count');
        }

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
                'lock_reason' => $this->access->lockReasonForLimit($limit, $episode, $user),
                'reader_progress_percent' => $episodeProgress[$episode->id] ?? null,
            ];
        });

        $progress = StoryCardPresenter::progressMap($user, collect([$story->id]));
        $liked = StoryCardPresenter::likedMap($user, collect([$story->id]));
        $bookmarked = StoryCardPresenter::bookmarkedMap($user, collect([$story->id]));

        return $this->ok([
            ...StoryCardPresenter::card($story, $user, $progress, $liked, $bookmarked),
            'genres' => $story->genres->map(fn ($g) => ['slug' => $g->slug, 'name' => $g->name]),
            'episodes' => $episodes,
        ]);
    }

    /**
     * A "view" only counts once per visitor per story per hour, not on every
     * page load/reload/re-render - refreshing the tab five times isn't five
     * reads. Visitor = the logged-in user's id, or (for guests) the same
     * per-browser session id already used for StoryView.session_hash - stable
     * across reloads for one visitor, unlike IP+UA which can collide (shared
     * office/mobile networks) or split (VPN, IP rotation).
     *
     * This alone gets you ~95% of what a Redis "seen" key + TTL buys you,
     * using the same portable Cache facade the rest of the app's caching goes
     * through (see HomeCache/EpisodeController) - identical behavior whether
     * CACHE_STORE is redis (Docker/cloud) or file/database (cPanel).
     *
     * Deliberately NOT also batching the DB write itself (accumulate a
     * counter in cache, flush to the `stories` row once a minute via a
     * scheduled job): dedup already cuts writes from "every reload" down to
     * "at most once per visitor per story per hour", which is the actual fix
     * for the reported problem, and it's a single indexed UPDATE + one INSERT
     * per unique view - trivial for Postgres/MySQL at this traffic level.
     * Batching on top would mainly help if writes became the bottleneck at
     * much higher scale, but it trades that for real complexity: an
     * "increment counter, then atomically read-and-reset it" step, which
     * Redis does natively (INCR + GETSET) but file/database cache stores
     * can't guarantee atomically - a flush racing a concurrent increment can
     * lose counts. Worth revisiting if `stories` writes ever show up as a
     * real bottleneck, but not before.
     */
    private function shouldCountView(Request $request, Story $story, ?\App\Models\User $user): bool
    {
        $visitor = $user ? "user:{$user->id}" : 'guest:'.$this->sessionHash($request);
        $key = "story-view-seen:{$story->id}:{$visitor}";

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, true, now()->addHour());

        return true;
    }
}

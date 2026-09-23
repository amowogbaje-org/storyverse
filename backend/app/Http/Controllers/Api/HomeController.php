<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\Genre;
use App\Models\Story;
use App\Services\BadgeMetricResolver;
use App\Support\HomeCache;
use App\Support\StoryCardPresenter;
use Illuminate\Http\Request;

/**
 * Single-call replacement for the four requests the homepage used to fire
 * separately (new-releases, popular, genres, next-badges). Combining them
 * cuts the homepage from 4 round trips (each paying its own TLS/auth/
 * server overhead) down to 1, and lets the shared, cacheable parts (story
 * lists, genre list) be computed once per request instead of once per
 * endpoint. Per-user overlays (likes/bookmarks/progress/badge progress)
 * are still computed fresh per request - nothing about caching changes
 * who sees what, only how many requests it takes to get there.
 */
class HomeController extends Controller
{
    public function __construct(private BadgeMetricResolver $resolver) {}

    public function index(Request $request)
    {
        $user = $this->currentUser($request);

        $newReleases = HomeCache::remember(HomeCache::NEW_RELEASES_KEY, fn () => Story::where('status', 'published')
            ->orderByDesc('published_at')
            ->with(['penName', 'categories'])
            ->limit(10)
            ->get());

        $popular = HomeCache::remember(HomeCache::POPULAR_KEY, fn () => Story::where('status', 'published')
            ->orderByDesc('views_count')
            ->with(['penName', 'categories'])
            ->limit(10)
            ->get());

        $genres = HomeCache::remember(HomeCache::GENRES_KEY, fn () => Genre::whereHas('stories', function ($q) {
            $q->where('status', 'published');
        })->get());

        // Per-user overlay for both story lists, batched together since a
        // story can appear in both new-releases and popular.
        $ids = $newReleases->pluck('id')->merge($popular->pluck('id'))->unique();
        $progress = StoryCardPresenter::progressMap($user, $ids);
        $liked = StoryCardPresenter::likedMap($user, $ids);
        $bookmarked = StoryCardPresenter::bookmarkedMap($user, $ids);

        $cardFor = fn (Story $s) => StoryCardPresenter::card($s, $user, $progress, $liked, $bookmarked);

        return $this->ok([
            'new_releases' => $newReleases->map($cardFor)->values(),
            'popular' => $popular->map($cardFor)->values(),
            'genres' => $genres->values(),
            // Guests never see badge progress, so this is just omitted
            // (never a 401) rather than forcing the frontend to branch on
            // auth state to decide whether to call a fourth endpoint.
            'next_badges' => $user ? $this->nextBadges($user, (int) $request->query('badges_limit', 1)) : [],
        ]);
    }

    /** Mirrors BadgeController::next() - see that method for the "why" on nulls/sorting. */
    private function nextBadges($user, int $limit)
    {
        $earnedIds = $user->badges()->pluck('badge_id');

        return Badge::whereNotIn('id', $earnedIds)->get()
            ->map(function (Badge $b) use ($user) {
                $current = $this->resolver->resolve($user, $b->criteria_type);

                if ($current === null) {
                    return null;
                }

                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'description' => $b->description,
                    'icon_url' => $b->icon_url,
                    'category' => $b->category,
                    'tier' => $b->tier,
                    'criteria_value' => $b->criteria_value,
                    'progress_current' => min($current, $b->criteria_value),
                    'progress_percent' => min(100, (int) round($current / max($b->criteria_value, 1) * 100)),
                    'remaining' => max(0, $b->criteria_value - $current),
                ];
            })
            ->filter()
            ->sortByDesc('progress_percent')
            ->values()
            ->take($limit);
    }
}

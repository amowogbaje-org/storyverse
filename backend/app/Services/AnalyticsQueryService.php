<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\PageView;
use App\Models\Payment;
use App\Models\ReadingProgress;
use App\Models\Story;
use App\Models\StoryBookmark;
use App\Models\StoryLike;
use App\Models\StoryPurchase;
use App\Models\StoryView;
use App\Models\Tip;
use App\Models\User;
use App\Models\UserActivityEvent;
use Illuminate\Support\Facades\DB;

/**
 * Read-only queries for the admin analytics dashboard: "is the app getting more
 * users, more reads, more engagement, more visits" per the project brief.
 *
 * Deliberately queries the underlying tables live rather than a nightly rollup —
 * at "just starting out" scale that's simpler and never drifts out of date. If
 * volumes grow enough that this gets slow, add a scheduled command that snapshots
 * daily totals into a dedicated table and swap these queries to read from that.
 */
class AnalyticsQueryService
{
    private const TIMESERIES_METRICS = [
        'new_users' => ['table' => 'users', 'column' => 'created_at'],
        'page_views' => ['table' => 'page_views', 'column' => 'created_at'],
        'story_views' => ['table' => 'story_views', 'column' => 'viewed_at'],
        'reads' => ['table' => 'reading_progress', 'column' => 'created_at'],
        'completed_reads' => ['table' => 'reading_progress', 'column' => 'completed_at', 'extra' => 'completed_at is not null'],
        'likes' => ['table' => 'story_likes', 'column' => 'created_at'],
        'bookmarks' => ['table' => 'story_bookmarks', 'column' => 'created_at'],
        'comments' => ['table' => 'comments', 'column' => 'created_at'],
    ];

    public function overview(): array
    {
        return [
            'totals' => [
                'users' => User::count(),
                'published_stories' => Story::where('status', 'published')->count(),
                'reads' => ReadingProgress::count(),
                'completed_reads' => ReadingProgress::whereNotNull('completed_at')->count(),
                'likes' => StoryLike::count(),
                'bookmarks' => StoryBookmark::count(),
                'comments' => Comment::count(),
                'page_views' => PageView::count(),
                'story_views' => StoryView::count(),
                'story_purchases' => StoryPurchase::where('status', 'success')->count(),
            ],
            'last_7_days' => $this->windowCounts(now()->subDays(7)),
            'last_30_days' => $this->windowCounts(now()->subDays(30)),
            // Payment (subscriptions, now historical only) + StoryPurchase (the
            // current, purchase-only revenue source) + Tip, combined per
            // currency - three different tables because each monetization
            // mechanism grew its own, but this is meant to answer "how much
            // came in", which doesn't care which table it's sitting in.
            'revenue_by_currency' => $this->revenueByCurrency(),
        ];
    }

    /** @return \Illuminate\Support\Collection<string,float> */
    private function revenueByCurrency(): \Illuminate\Support\Collection
    {
        $fromPayments = Payment::where('status', 'success')
            ->select('currency', DB::raw('sum(amount) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $fromPurchases = StoryPurchase::where('status', 'success')
            ->select('currency', DB::raw('sum(amount) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $fromTips = Tip::where('status', 'success')
            ->select('currency', DB::raw('sum(amount) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');

        return collect([$fromPayments, $fromPurchases, $fromTips])
            ->reduce(fn ($carry, $bucket) => $bucket->reduce(
                fn ($c, $amount, $currency) => $c->put($currency, ($c->get($currency) ?? 0) + $amount),
                $carry
            ), collect());
    }

    /**
     * Same counts as the dashboard's last_7_days/last_30_days blocks, just for
     * "since this time yesterday" - what the daily analytics summary email uses.
     *
     * @return array<string,int>
     */
    public function last24Hours(): array
    {
        return $this->windowCounts(now()->subDay());
    }

    /** @return array<string,int> */
    private function windowCounts(\Carbon\Carbon $since): array
    {
        return [
            'new_users' => User::where('created_at', '>=', $since)->count(),
            'reads' => ReadingProgress::where('created_at', '>=', $since)->count(),
            'completed_reads' => ReadingProgress::where('completed_at', '>=', $since)->count(),
            'page_views' => PageView::where('created_at', '>=', $since)->count(),
            'likes' => StoryLike::where('created_at', '>=', $since)->count(),
            'bookmarks' => StoryBookmark::where('created_at', '>=', $since)->count(),
            'comments' => Comment::where('created_at', '>=', $since)->count(),
            'new_story_purchases' => StoryPurchase::where('status', 'success')->where('created_at', '>=', $since)->count(),
        ];
    }

    /** Daily series for one metric, for the last $days days. */
    public function timeseries(string $metric, int $days = 30): array
    {
        $config = self::TIMESERIES_METRICS[$metric] ?? null;

        if (! $config) {
            return [];
        }

        $since = now()->subDays($days)->startOfDay();

        $query = DB::table($config['table'])
            ->where($config['column'], '>=', $since)
            ->selectRaw("date({$config['column']}) as day, count(*) as value")
            ->groupBy('day')
            ->orderBy('day');

        if (isset($config['extra'])) {
            $query->whereRaw($config['extra']);
        }

        $rows = $query->get()->keyBy(fn ($r) => (string) $r->day);

        // Fill in zero-count days so the chart doesn't have gaps.
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $series[] = ['date' => $day, 'value' => (int) ($rows[$day]->value ?? 0)];
        }

        return $series;
    }

    public function topStories(int $days = 30, int $limit = 10): array
    {
        $since = now()->subDays($days);

        return Story::where('status', 'published')
            ->with('penName')
            ->withCount([
                'likes as recent_likes' => fn ($q) => $q->where('created_at', '>=', $since),
                'bookmarks as recent_bookmarks' => fn ($q) => $q->where('created_at', '>=', $since),
                'comments as recent_comments' => fn ($q) => $q->where('created_at', '>=', $since),
            ])
            ->orderByDesc('views_count')
            ->limit($limit)
            ->get()
            ->map(fn (Story $s) => [
                'title' => $s->title,
                'slug' => $s->slug,
                'author_display_name' => $s->penName?->display_name,
                'views_count' => $s->views_count,
                'recent_likes' => $s->recent_likes,
                'recent_bookmarks' => $s->recent_bookmarks,
                'recent_comments' => $s->recent_comments,
            ])
            ->all();
    }

    /**
     * Guest visit -> registration -> first read -> premium conversion, over the
     * whole platform lifetime. A simple funnel, not session-attributed (page_views
     * aren't tied to a registration event by session), which is a reasonable
     * limitation to flag rather than fake precision that isn't there yet.
     */
    public function funnel(): array
    {
        return [
            'site_visits' => PageView::distinct('session_hash')->count('session_hash'),
            'registered_users' => User::count(),
            'users_who_read_something' => ReadingProgress::distinct('user_id')->count('user_id'),
            'users_who_purchased' => StoryPurchase::where('status', 'success')->distinct('user_id')->count('user_id'),
        ];
    }

    /** Event counts by type over a window — cheap engagement breakdown from the activity log. */
    public function eventBreakdown(int $days = 30): array
    {
        return UserActivityEvent::where('created_at', '>=', now()->subDays($days))
            ->select('event_type', DB::raw('count(*) as total'))
            ->groupBy('event_type')
            ->orderByDesc('total')
            ->pluck('total', 'event_type')
            ->all();
    }

    /**
     * Weekly signup-cohort retention: of the people who first showed up in a
     * given week, what fraction were still doing *anything* (a page view or
     * touching their reading progress) 1/2/3/4 weeks later. This is the
     * "are we actually holding onto readers" view that raw totals don't
     * answer - a chart can go up and to the right purely on new signups
     * while everyone from a month ago has quietly left.
     *
     * "Active" is deliberately broad (any page view OR any reading_progress
     * touch) rather than "read something", since a returning-but-browsing
     * visit is still a retention win worth counting.
     *
     * @return array<int, array{cohort_start: string, cohort_size: int, weeks: array<int, float|null>}>
     */
    public function retentionCohorts(int $cohortWeeks = 8, int $trackWeeks = 5): array
    {
        $firstCohortStart = now()->subWeeks($cohortWeeks)->startOfWeek();

        $cohorts = User::where('created_at', '>=', $firstCohortStart)
            ->select('id', 'created_at')
            ->get()
            ->groupBy(fn (User $u) => $u->created_at->startOfWeek()->toDateString());

        $rows = [];

        for ($i = $cohortWeeks - 1; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $key = $weekStart->toDateString();
            $users = $cohorts->get($key) ?? collect();
            $userIds = $users->pluck('id');
            $cohortSize = $userIds->count();

            $weeks = [];
            for ($w = 0; $w < $trackWeeks; $w++) {
                $windowStart = (clone $weekStart)->addWeeks($w);
                $windowEnd = (clone $windowStart)->addWeek();

                // Nothing to measure yet for a week that hasn't happened, and
                // no readers to measure for an empty cohort - both render as
                // null (shown as "—") rather than a misleading 0%.
                if ($cohortSize === 0 || $windowStart->isFuture()) {
                    $weeks[] = null;
                    continue;
                }

                $activeCount = $this->activeUserIdsBetween($windowStart, $windowEnd)
                    ->intersect($userIds)
                    ->count();

                $weeks[] = round(($activeCount / $cohortSize) * 100, 1);
            }

            $rows[] = [
                'cohort_start' => $key,
                'cohort_size' => $cohortSize,
                'weeks' => $weeks,
            ];
        }

        return $rows;
    }

    /** Distinct user IDs with a page view or reading-progress touch in [start, end). */
    private function activeUserIdsBetween(\Carbon\Carbon $start, \Carbon\Carbon $end): \Illuminate\Support\Collection
    {
        $fromViews = DB::table('page_views')
            ->whereNotNull('user_id')
            ->whereBetween('created_at', [$start, $end])
            ->distinct()
            ->pluck('user_id');

        $fromReading = DB::table('reading_progress')
            ->whereBetween('last_read_at', [$start, $end])
            ->distinct()
            ->pluck('user_id');

        return $fromViews->merge($fromReading)->unique();
    }

    /**
     * Classic "stickiness" - average daily active users divided by monthly
     * active users, over the trailing 30 days. A rough read on habit: a
     * ratio near 1 means people show up most days, near 0 means they drift
     * back only occasionally. Google/Facebook-style DAU/MAU.
     */
    public function stickiness(): array
    {
        $monthStart = now()->subDays(30);

        $mau = $this->activeUserIdsBetween($monthStart, now())->count();

        $dailyActive = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $dailyActive[] = $this->activeUserIdsBetween($day, (clone $day)->addDay())->count();
        }

        $avgDau = $dailyActive ? array_sum($dailyActive) / count($dailyActive) : 0;

        return [
            'dau_avg_30d' => round($avgDau, 1),
            'mau_30d' => $mau,
            'stickiness_ratio' => $mau > 0 ? round($avgDau / $mau, 3) : null,
        ];
    }

    /**
     * Per-episode read-through for one story: how many readers reached each
     * episode, how far they typically got, and what fraction of the
     * previous episode's readers carried on to this one. This is the
     * "where exactly do people quit this story" view - a single completion
     * rate for the whole story hides which specific episode is the leak.
     *
     * @return array<int, array{episode_id: int, episode_number: int, title: string, readers: int, avg_progress_percent: float, completed: int, carried_over_percent: float|null}>
     */
    public function episodeDropoff(int $storyId): array
    {
        $episodes = DB::table('episodes')
            ->where('story_id', $storyId)
            ->orderBy('episode_number')
            ->get(['id', 'episode_number', 'title']);

        $stats = DB::table('reading_progress')
            ->where('story_id', $storyId)
            ->select(
                'episode_id',
                DB::raw('count(distinct user_id) as readers'),
                DB::raw('avg(progress_percent) as avg_progress'),
                DB::raw('sum(case when completed_at is not null then 1 else 0 end) as completed')
            )
            ->groupBy('episode_id')
            ->get()
            ->keyBy('episode_id');

        $rows = [];
        $previousReaders = null;

        foreach ($episodes as $episode) {
            $stat = $stats->get($episode->id);
            $readers = (int) ($stat->readers ?? 0);

            $rows[] = [
                'episode_id' => $episode->id,
                'episode_number' => $episode->episode_number,
                'title' => $episode->title,
                'readers' => $readers,
                'avg_progress_percent' => round((float) ($stat->avg_progress ?? 0), 1),
                'completed' => (int) ($stat->completed ?? 0),
                'carried_over_percent' => $previousReaders && $previousReaders > 0
                    ? round(($readers / $previousReaders) * 100, 1)
                    : null,
            ];

            $previousReaders = $readers;
        }

        return $rows;
    }
}

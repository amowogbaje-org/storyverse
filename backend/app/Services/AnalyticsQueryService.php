<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\PageView;
use App\Models\Payment;
use App\Models\ReadingProgress;
use App\Models\Story;
use App\Models\StoryBookmark;
use App\Models\StoryLike;
use App\Models\StoryView;
use App\Models\Subscription;
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
                'active_subscriptions' => Subscription::where('status', 'active')
                    ->where('current_period_end', '>', now())
                    ->count(),
            ],
            'last_7_days' => $this->windowCounts(now()->subDays(7)),
            'last_30_days' => $this->windowCounts(now()->subDays(30)),
            'revenue_by_currency' => Payment::where('status', 'success')
                ->select('currency', DB::raw('sum(amount) as total'))
                ->groupBy('currency')
                ->pluck('total', 'currency'),
        ];
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
            'new_subscriptions' => Subscription::where('created_at', '>=', $since)->count(),
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
            'users_with_active_subscription' => Subscription::where('status', 'active')
                ->where('current_period_end', '>', now())
                ->distinct('user_id')
                ->count('user_id'),
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
}

<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivityEvent;
use Illuminate\Support\Facades\DB;

/**
 * Resolves a user's current value for a badge's criteria_type, so the listener
 * can compare it against criteria_value. Each badge in the seeder declares one
 * of the keys below. Every criteria_type currently in the seeder has a
 * resolver; an unrecognized one returns null and is simply skipped (never
 * auto-awarded) rather than erroring, so adding a new badge type is safe to
 * roll out ahead of writing its resolver.
 */
class BadgeMetricResolver
{
    public function resolve(User $user, string $criteriaType): ?int
    {
        return match ($criteriaType) {
            'episodes_completed' => $this->countEvents($user, 'episode_completed'),
            'stories_completed' => $this->countEvents($user, 'story_completed'),
            'comments_posted' => $this->countEvents($user, 'comment_posted'),
            'bookmarks_made' => $this->countEvents($user, 'story_bookmarked'),
            'likes_given' => $this->countEvents($user, 'story_liked'),
            'shares_made' => $this->countEvents($user, 'story_shared'),
            'ai_search_uses' => $this->countEvents($user, 'ai_search_used'),
            'streak_days' => $user->current_streak_days,
            'categories_explored' => $this->distinctCategoriesRead($user),
            'genres_explored' => $this->distinctGenresRead($user),
            'author_stories_completed_max' => $this->maxStoriesCompletedForOneAuthor($user),
            'cumulative_spend' => $this->cumulativeSpend($user),
            'premium_episodes_unlocked_stories' => $this->distinctPremiumStoriesUnlocked($user),
            'early_comments' => $this->earlyComments($user),
            'late_night_reads' => $this->readsInHourRange($user, 0, 4),
            'early_morning_reads' => $this->readsInHourRange($user, 5, 7),
            'return_after_absence' => $this->hasReturnedAfterAbsence($user),
            'new_release_reads' => $this->newReleaseReads($user),
            'author_all_stories_read' => $this->hasCompletedAllStoriesForAnyAuthor($user),
            'all_categories_explored' => $this->hasExploredAllCategories($user),
            'bookmarked_before_trending' => $this->earlyBookmarksNowTrending($user),
            'referrals_verified' => $user->referrals()->whereNotNull('email_verified_at')->count(),
            default => null,
        };
    }

    private function countEvents(User $user, string $eventType): int
    {
        return UserActivityEvent::where('user_id', $user->id)
            ->where('event_type', $eventType)
            ->count();
    }

    private function distinctCategoriesRead(User $user): int
    {
        return DB::table('reading_progress')
            ->join('category_story', 'category_story.story_id', '=', 'reading_progress.story_id')
            ->where('reading_progress.user_id', $user->id)
            ->distinct('category_story.category_id')
            ->count('category_story.category_id');
    }

    private function distinctGenresRead(User $user): int
    {
        return DB::table('reading_progress')
            ->join('genre_story', 'genre_story.story_id', '=', 'reading_progress.story_id')
            ->where('reading_progress.user_id', $user->id)
            ->distinct('genre_story.genre_id')
            ->count('genre_story.genre_id');
    }

    private function maxStoriesCompletedForOneAuthor(User $user): int
    {
        return (int) UserActivityEvent::where('user_id', $user->id)
            ->where('event_type', 'story_completed')
            ->get()
            ->pluck('metadata.pen_name_id')
            ->filter()
            ->countBy()
            ->max() ?: 0;
    }

    /**
     * Total spend across both revenue mechanisms this platform has ever had:
     * payments (subscriptions, historical only - see the subscription
     * removal notes elsewhere) and story_purchases (the current one). Only
     * summing `payments` here would mean these badges silently stopped
     * triggering for anyone the moment subscriptions were removed, even
     * though people are still very much spending money via story purchases.
     */
    private function cumulativeSpend(User $user): int
    {
        // NOTE: naive sum, no currency conversion - fine while pricing is per-country
        // and users mostly pay in one currency, but worth revisiting if that changes.
        $fromPayments = (float) DB::table('payments')
            ->where('user_id', $user->id)
            ->where('status', 'success')
            ->sum('amount');

        $fromPurchases = (float) DB::table('story_purchases')
            ->where('user_id', $user->id)
            ->where('status', 'success')
            ->sum('amount');

        return (int) ($fromPayments + $fromPurchases);
    }

    /** How many distinct premium stories this reader has read past the paywall on. */
    private function distinctPremiumStoriesUnlocked(User $user): int
    {
        return DB::table('reading_progress')
            ->join('stories', 'stories.id', '=', 'reading_progress.story_id')
            ->join('episodes', 'episodes.id', '=', 'reading_progress.episode_id')
            ->where('reading_progress.user_id', $user->id)
            ->where('stories.access_type', 'premium')
            ->where('episodes.episode_number', '>', 5) // past the registered-reader free tier - see StoryAccessService
            ->distinct('stories.id')
            ->count('stories.id');
    }

    /**
     * Reads whose last-known timestamp falls in a given UTC hour range. An
     * approximation, not per-session: `reading_progress` holds one row per
     * user+episode (updated on every progress tick), so this reflects the most
     * recent read time for each episode, not every individual read. Also UTC,
     * not the reader's local time - we don't store a per-user timezone, only
     * country_code, which isn't a reliable enough proxy to convert from safely.
     */
    private function readsInHourRange(User $user, int $startHour, int $endHourInclusive): int
    {
        $hourExpr = DB::connection()->getDriverName() === 'pgsql'
            ? 'extract(hour from last_read_at)'
            : 'hour(last_read_at)';

        return DB::table('reading_progress')
            ->where('user_id', $user->id)
            ->whereNotNull('last_read_at')
            ->whereRaw("{$hourExpr} between ? and ?", [$startHour, $endHourInclusive])
            ->count();
    }

    /**
     * 1 if there's ever been a 14+ day gap between two of this reader's episode
     * reads, followed by another read - i.e. they lapsed and came back.
     */
    private function hasReturnedAfterAbsence(User $user): int
    {
        $days = DB::table('reading_progress')
            ->where('user_id', $user->id)
            ->whereNotNull('last_read_at')
            ->orderBy('last_read_at')
            ->pluck('last_read_at')
            ->map(fn ($d) => \Carbon\Carbon::parse($d))
            ->values();

        for ($i = 1; $i < $days->count(); $i++) {
            if ($days[$i - 1]->diffInDays($days[$i]) >= 14) {
                return 1;
            }
        }

        return 0;
    }

    /** Reads that happened within 7 days of the story's publish date. */
    private function newReleaseReads(User $user): int
    {
        $withinDays = DB::connection()->getDriverName() === 'pgsql'
            ? "reading_progress.created_at <= stories.published_at + interval '7 days'"
            : 'reading_progress.created_at <= stories.published_at + interval 7 day';

        return DB::table('reading_progress')
            ->join('stories', 'stories.id', '=', 'reading_progress.story_id')
            ->where('reading_progress.user_id', $user->id)
            ->whereNotNull('stories.published_at')
            ->whereRaw($withinDays)
            ->distinct('stories.id')
            ->count('stories.id');
    }

    /**
     * 1 if this reader has completed every published story from at least one
     * author (pen name) - i.e. they've caught up on someone's entire catalog.
     */
    private function hasCompletedAllStoriesForAnyAuthor(User $user): int
    {
        $completedByPenName = UserActivityEvent::where('user_id', $user->id)
            ->where('event_type', 'story_completed')
            ->get()
            ->pluck('metadata.pen_name_id')
            ->filter()
            ->countBy();

        foreach ($completedByPenName as $penNameId => $completedCount) {
            $totalPublished = DB::table('stories')
                ->where('pen_name_id', $penNameId)
                ->where('status', 'published')
                ->count();

            if ($totalPublished > 0 && $completedCount >= $totalPublished) {
                return 1;
            }
        }

        return 0;
    }

    /** Comments posted within 24 hours of the episode they're on going live. */
    private function earlyComments(User $user): int
    {
        $within24h = DB::connection()->getDriverName() === 'pgsql'
            ? "comments.created_at <= episodes.published_at + interval '24 hours'"
            : 'comments.created_at <= episodes.published_at + interval 24 hour';

        return DB::table('comments')
            ->join('episodes', 'episodes.id', '=', 'comments.episode_id')
            ->where('comments.user_id', $user->id)
            ->whereNotNull('episodes.published_at')
            ->whereRaw($within24h)
            ->count();
    }

    private function hasExploredAllCategories(User $user): int
    {
        $totalCategories = DB::table('categories')->count();

        return $totalCategories > 0 && $this->distinctCategoriesRead($user) >= $totalCategories ? 1 : 0;
    }

    /**
     * Count of this reader's bookmarks that turned out to be prescient: the
     * story had very few views at the moment they bookmarked it
     * (views_count_at_bookmark <= tastemaker_early_views_threshold), and has
     * since grown into one of the platform's most-viewed
     * (stories.views_count, right now, >= tastemaker_trending_views_threshold).
     *
     * Both thresholds are configurable (config/badges.php) since "few views"
     * and "trending" only mean something relative to your platform's actual
     * traffic - there's no universal number that's right for every stage of
     * growth, so tune them as the numbers on the site change.
     */
    private function earlyBookmarksNowTrending(User $user): int
    {
        return DB::table('story_bookmarks')
            ->join('stories', 'stories.id', '=', 'story_bookmarks.story_id')
            ->where('story_bookmarks.user_id', $user->id)
            ->where('story_bookmarks.views_count_at_bookmark', '<=', config('badges.tastemaker_early_views_threshold'))
            ->where('stories.views_count', '>=', config('badges.tastemaker_trending_views_threshold'))
            ->count();
    }
}

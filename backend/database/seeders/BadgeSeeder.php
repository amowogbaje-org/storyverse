<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->badges() as $badge) {
            Badge::updateOrCreate(['slug' => $badge['slug']], $badge);
        }
    }

    private function badges(): array
    {
        return [
            // Reading
            $this->b('First Page', 'reading', 'bronze', 'episodes_completed', 1),
            $this->b('Bookworm', 'reading', 'bronze', 'episodes_completed', 10),
            $this->b('Page Turner', 'reading', 'silver', 'episodes_completed', 50),
            $this->b('Story Devourer', 'reading', 'gold', 'episodes_completed', 200, 'bonus_access', ['free_premium_days' => 3]),
            $this->b('First Finish', 'reading', 'bronze', 'stories_completed', 1),
            $this->b('Serial Reader', 'reading', 'silver', 'stories_completed', 10, 'recommendation'),
            $this->b('Library Legend', 'reading', 'gold', 'stories_completed', 50, 'bonus_access', ['free_premium_days' => 7]),
            $this->b('Marathoner', 'reading', 'platinum', 'episodes_completed', 500, 'bonus_access', ['free_premium_days' => 30]),

            // Interaction / Social
            $this->b('First Words', 'social', 'bronze', 'comments_posted', 1),
            $this->b('Conversationalist', 'social', 'silver', 'comments_posted', 25),
            $this->b('Community Voice', 'social', 'gold', 'comments_posted', 100, 'recommendation'),
            $this->b('Curator', 'social', 'bronze', 'bookmarks_made', 10),
            $this->b('Collector', 'social', 'silver', 'bookmarks_made', 50),
            $this->b('Superfan', 'social', 'silver', 'likes_given', 100),
            $this->b('Early Voice', 'social', 'gold', 'early_comments', 20, 'bonus_access', ['free_premium_days' => 3]),
            // Fixed: now resolvable - see BadgeMetricResolver::earlyBookmarksNowTrending()
            // and the views_count_at_bookmark snapshot column on story_bookmarks.
            $this->b('Tastemaker', 'social', 'platinum', 'bookmarked_before_trending', 1, 'recommendation'),
            $this->b('First Share', 'social', 'bronze', 'shares_made', 1),
            $this->b('Promoter', 'social', 'silver', 'shares_made', 10, 'recommendation'),
            $this->b('Hype Machine', 'social', 'gold', 'shares_made', 50, 'bonus_access', ['free_premium_days' => 3]),

            // Referrals
            $this->b('Recruiter', 'referrals', 'bronze', 'referrals_verified', 1),
            $this->b('Ambassador', 'referrals', 'silver', 'referrals_verified', 5, 'recommendation'),
            // The actual free-month reward for hitting 10 is granted directly by
            // ReferralService regardless of config('badges.rewards_enabled') - this
            // badge's own reward_type is 'none' so it doesn't also grant a second,
            // separate reward through the generic badge-reward path once that's
            // turned on.
            $this->b('Super Referrer', 'referrals', 'gold', 'referrals_verified', 10),
            $this->b('Referral Legend', 'referrals', 'platinum', 'referrals_verified', 25, 'recommendation'),

            // Spending / Subscription
            $this->b('First Unlock', 'spending', 'bronze', 'cumulative_spend', 1),
            $this->b('Supporter', 'spending', 'bronze', 'premium_months_consecutive', 1),
            $this->b('Loyal Patron', 'spending', 'silver', 'premium_months_consecutive', 3),
            $this->b('Devoted Patron', 'spending', 'gold', 'premium_months_consecutive', 6, 'bonus_access', ['free_premium_days' => 30]),
            $this->b('Annual VIP', 'spending', 'gold', 'annual_plan_purchased', 1),
            $this->b('Big Spender', 'spending', 'silver', 'cumulative_spend', 30, 'recommendation'),
            $this->b("Patron's Circle", 'spending', 'platinum', 'premium_months_consecutive', 12, 'bonus_access', ['free_premium_days' => 30]),
            $this->b('Wide Reader', 'spending', 'silver', 'premium_episodes_unlocked_stories', 10),

            // Streak / Engagement
            $this->b('Daily Visitor', 'streak', 'bronze', 'streak_days', 3),
            $this->b('Weekly Habit', 'streak', 'bronze', 'streak_days', 7),
            $this->b('Fortnight Fanatic', 'streak', 'silver', 'streak_days', 14),
            $this->b('Monthly Marathoner', 'streak', 'gold', 'streak_days', 30, 'bonus_access', ['free_premium_days' => 3]),
            $this->b('Unbreakable', 'streak', 'platinum', 'streak_days', 90, 'bonus_access', ['free_premium_days' => 14]),
            $this->b('Night Owl', 'streak', 'bronze', 'late_night_reads', 10),
            $this->b('Early Bird', 'streak', 'bronze', 'early_morning_reads', 10),
            $this->b('Comeback', 'streak', 'silver', 'return_after_absence', 1, 'recommendation'),

            // Discovery
            $this->b('Explorer', 'discovery', 'bronze', 'categories_explored', 3),
            $this->b('Genre Hopper', 'discovery', 'silver', 'genres_explored', 8),
            $this->b('AI Search Novice', 'discovery', 'bronze', 'ai_search_uses', 5),
            $this->b('AI Search Pro', 'discovery', 'silver', 'ai_search_uses', 25),
            $this->b('New Release Fan', 'discovery', 'silver', 'new_release_reads', 5, 'recommendation'),
            $this->b('Author Fan', 'discovery', 'bronze', 'author_stories_completed_max', 5),
            $this->b('Author Superfan', 'discovery', 'gold', 'author_all_stories_read', 1, 'bonus_access', ['free_premium_days' => 3]),
            $this->b('Well-Rounded', 'discovery', 'platinum', 'all_categories_explored', 1, 'bonus_access', ['free_premium_days' => 7]),
        ];
    }

    private function b(
        string $name,
        string $category,
        string $tier,
        string $criteriaType,
        int $criteriaValue,
        string $rewardType = 'none',
        ?array $rewardPayload = null,
    ): array {
        return [
            'name' => $name,
            'slug' => str($name)->slug(),
            'description' => $this->describe($criteriaType, $criteriaValue),
            'icon_url' => null,
            'category' => $category,
            'tier' => $tier,
            'criteria_type' => $criteriaType,
            'criteria_value' => $criteriaValue,
            'reward_type' => $rewardType,
            'reward_payload' => $rewardPayload,
        ];
    }

    /**
     * Turns a criteria_type + criteria_value into plain-language copy shown
     * on the badge (frontend BadgesPage/BadgeGrid) and in the unlock email -
     * this is the "what do I actually have to do" text, so it needs to read
     * like an instruction, not a database field name.
     */
    private function describe(string $criteriaType, int $value): string
    {
        $ep = Str::plural('episode', $value);
        $story = Str::plural('story', $value);
        $day = Str::plural('day', $value);
        $month = Str::plural('month', $value);
        $category = Str::plural('category', $value);
        $genre = Str::plural('genre', $value);
        $comment = Str::plural('comment', $value);
        $time = Str::plural('time', $value);

        return match ($criteriaType) {
            'episodes_completed' => "Finish {$value} {$ep}, across any stories.",
            'stories_completed' => "Complete {$value} full {$story} (every published episode read).",
            'comments_posted' => "Post {$value} {$comment} on any episodes.",
            'bookmarks_made' => "Bookmark {$value} {$story}.",
            'likes_given' => "Like {$value} {$story}.",
            'ai_search_uses' => "Use AI search {$value} {$time} to find something to read.",
            'streak_days' => "Read on {$value} days in a row.",
            'categories_explored' => "Read at least one story from {$value} different {$category}.",
            'genres_explored' => "Read at least one story from {$value} different {$genre}.",
            'author_stories_completed_max' => "Complete {$value} {$story} from the same author.",
            'cumulative_spend' => "Reach {$value} total spent on the platform, across any purchases.",
            'premium_months_consecutive' => "Stay subscribed for {$value} consecutive {$month}.",
            'annual_plan_purchased' => 'Purchase an annual subscription plan.',
            'premium_episodes_unlocked_stories' => "Unlock premium episodes in {$value} different {$story}.",
            'early_comments' => "Post {$value} {$comment} within 24 hours of an episode going live.",
            'late_night_reads' => "Read {$value} {$ep} between midnight and 4am.",
            'early_morning_reads' => "Read {$value} {$ep} between 5am and 7am.",
            'return_after_absence' => 'Come back and keep reading after taking a 14+ day break.',
            'new_release_reads' => "Read {$value} {$ep} within a week of their release.",
            'author_all_stories_read' => "Finish every published story from one author.",
            'all_categories_explored' => 'Read at least one story from every category on the platform.',
            'bookmarked_before_trending' => "Bookmark {$value} {$story} while it still had very few views, before it went on to become one of the platform's most-viewed.",
            'shares_made' => "Share {$value} {$story} or {$ep}, to WhatsApp, Facebook, X, LinkedIn, or anywhere else.",
            'referrals_verified' => "Refer {$value} verified ".Str::plural('reader', $value)." to Storyverse using your referral link.",
            default => "Earn the {$value} required for this badge.",
        };
    }
}

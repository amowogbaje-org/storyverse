<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

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
            $this->b('Tastemaker', 'social', 'platinum', 'bookmarked_before_trending', 1, 'recommendation'), // TODO: needs a bookmark-time view-count snapshot, see BadgeMetricResolver

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
            'description' => "Earn the {$name} badge.", // placeholder copy - worth writing real descriptions before launch
            'icon_url' => null,
            'category' => $category,
            'tier' => $tier,
            'criteria_type' => $criteriaType,
            'criteria_value' => $criteriaValue,
            'reward_type' => $rewardType,
            'reward_payload' => $rewardPayload,
        ];
    }
}

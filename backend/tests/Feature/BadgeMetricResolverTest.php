<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Services\BadgeMetricResolver;
use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class BadgeMetricResolverTest extends TestCase
{
    use CreatesStoryFixtures;

    /**
     * annual_plan_purchased and premium_months_consecutive were removed along
     * with the subscription-tied badges that used them (Annual VIP, Supporter,
     * Loyal Patron, etc - see BadgeSeeder) when subscriptions were removed as
     * the premium-access mechanism. They now behave like any other
     * unrecognized criteria_type: resolve() returns null and the badge is
     * simply never auto-awarded, same as bookmarked_before_trending below.
     */
    public function test_annual_plan_purchased_and_premium_months_consecutive_are_no_longer_resolvable(): void
    {
        $reader = $this->createReader();
        $resolver = app(BadgeMetricResolver::class);

        $this->assertNull($resolver->resolve($reader, 'annual_plan_purchased'));
        $this->assertNull($resolver->resolve($reader, 'premium_months_consecutive'));
    }

    /**
     * cumulative_spend backs the "First Unlock"/"Big Spender" badges - it
     * used to only sum the payments table (subscriptions), which would have
     * silently stopped counting anything the moment subscriptions were
     * removed, even though story purchases are real spend too.
     */
    public function test_cumulative_spend_counts_story_purchases(): void
    {
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $reader = $this->createReader();

        $story->purchases()->create([
            'user_id' => $reader->id, 'gateway' => 'stripe', 'gateway_reference' => 'ref-1',
            'amount' => 12, 'currency' => 'USD', 'status' => 'success',
        ]);

        $this->assertSame(12, app(BadgeMetricResolver::class)->resolve($reader, 'cumulative_spend'));
    }

    public function test_cumulative_spend_ignores_a_pending_purchase(): void
    {
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $reader = $this->createReader();

        $story->purchases()->create([
            'user_id' => $reader->id, 'gateway' => 'stripe', 'gateway_reference' => 'ref-2',
            'amount' => 12, 'currency' => 'USD', 'status' => 'pending',
        ]);

        $this->assertSame(0, app(BadgeMetricResolver::class)->resolve($reader, 'cumulative_spend'));
    }

    public function test_all_categories_explored_requires_reading_progress_in_every_category(): void
    {
        Category::query()->delete();
        $cat1 = Category::create(['name' => 'Fantasy', 'slug' => 'fantasy']);
        $cat2 = Category::create(['name' => 'Romance', 'slug' => 'romance']);

        $reader = $this->createReader();
        ['story' => $story1, 'episodes' => $episodes1] = $this->createStoryWithEpisodes(['category_id' => $cat1->id], episodeCount: 1);

        \App\Models\ReadingProgress::create([
            'user_id' => $reader->id, 'episode_id' => $episodes1->first()->id,
            'story_id' => $story1->id, 'progress_percent' => 50, 'last_read_at' => now(),
        ]);

        $resolver = app(BadgeMetricResolver::class);
        $this->assertSame(0, $resolver->resolve($reader, 'all_categories_explored'));

        ['story' => $story2, 'episodes' => $episodes2] = $this->createStoryWithEpisodes(['category_id' => $cat2->id], episodeCount: 1);
        \App\Models\ReadingProgress::create([
            'user_id' => $reader->id, 'episode_id' => $episodes2->first()->id,
            'story_id' => $story2->id, 'progress_percent' => 50, 'last_read_at' => now(),
        ]);

        $this->assertSame(1, $resolver->resolve($reader, 'all_categories_explored'));
    }

    public function test_bookmarked_before_trending_has_no_resolver_and_is_skipped(): void
    {
        $reader = $this->createReader();

        $this->assertNull(app(BadgeMetricResolver::class)->resolve($reader, 'bookmarked_before_trending'));
    }
}

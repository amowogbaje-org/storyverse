<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Services\BadgeMetricResolver;
use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class BadgeMetricResolverTest extends TestCase
{
    use CreatesStoryFixtures;

    public function test_annual_plan_purchased_is_true_only_after_a_successful_yearly_payment(): void
    {
        $reader = $this->createReader();
        $plan = SubscriptionPlan::create([
            'name' => 'Annual', 'country_code' => 'US', 'currency' => 'USD',
            'price' => 100, 'billing_interval' => 'year', 'is_active' => true,
        ]);

        $resolver = app(BadgeMetricResolver::class);
        $this->assertSame(0, $resolver->resolve($reader, 'annual_plan_purchased'));

        Payment::create([
            'user_id' => $reader->id, 'plan_id' => $plan->id, 'gateway' => 'stripe',
            'gateway_reference' => 'ref1', 'amount' => 100, 'currency' => 'USD', 'status' => 'success',
        ]);

        $this->assertSame(1, $resolver->resolve($reader, 'annual_plan_purchased'));
    }

    public function test_consecutive_paid_months_counts_the_longest_unbroken_run(): void
    {
        $reader = $this->createReader();
        $plan = SubscriptionPlan::create([
            'name' => 'Monthly', 'country_code' => 'US', 'currency' => 'USD',
            'price' => 10, 'billing_interval' => 'month', 'is_active' => true,
        ]);

        foreach ([now()->subMonths(5), now()->subMonths(4), now()->subMonths(3), now()->subMonths(1)] as $when) {
            Payment::create([
                'user_id' => $reader->id, 'plan_id' => $plan->id, 'gateway' => 'stripe',
                'gateway_reference' => 'ref-'.$when->timestamp, 'amount' => 10, 'currency' => 'USD',
                'status' => 'success', 'created_at' => $when,
            ]);
        }

        // 5,4,3 months ago are consecutive (streak of 3); 1 month ago is isolated.
        $this->assertSame(3, app(BadgeMetricResolver::class)->resolve($reader, 'premium_months_consecutive'));
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

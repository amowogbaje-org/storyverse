<?php

namespace Tests\Feature;

use App\Services\PlatformMetricsService;
use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class StoryAccessTest extends TestCase
{
    use CreatesStoryFixtures;

    private function bootstrapPhase(): void
    {
        $this->mock(PlatformMetricsService::class, function ($mock) {
            $mock->shouldReceive('isMonetizationEnabled')->andReturn(false);
        });
    }

    public function test_guest_can_read_only_the_first_two_episodes_of_a_premium_story_once_monetization_is_on(): void
    {
        $this->monetizationEnabled();
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);

        $this->getJson("/api/v1/stories/{$story->slug}/episodes/2")->assertStatus(200);
        $this->getJson("/api/v1/stories/{$story->slug}/episodes/3")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'guest_limit');
    }

    public function test_registered_reader_can_read_five_episodes_of_a_premium_story_once_monetization_is_on(): void
    {
        $this->monetizationEnabled();
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $reader = $this->createReader();

        $this->getJson("/api/v1/stories/{$story->slug}/episodes/5", $this->bearerFor($reader))->assertStatus(200);
        $this->getJson("/api/v1/stories/{$story->slug}/episodes/6", $this->bearerFor($reader))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'premium_required');
    }

    public function test_registered_reader_has_full_access_to_a_free_story_even_after_monetization_is_on(): void
    {
        $this->monetizationEnabled();
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'free']);
        $reader = $this->createReader();

        $this->getJson("/api/v1/stories/{$story->slug}/episodes/6", $this->bearerFor($reader))->assertStatus(200);
    }

    public function test_guest_still_only_gets_two_episodes_of_a_free_story(): void
    {
        $this->monetizationEnabled();
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'free']);

        $this->getJson("/api/v1/stories/{$story->slug}/episodes/3")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'guest_limit');
    }

    /**
     * Subscriptions are no longer the premium-access mechanism (see
     * StoryAccessService/BonusAccessService) - a plain active subscription
     * row (if one somehow still exists) grants nothing on its own anymore.
     */
    public function test_an_active_subscription_alone_no_longer_grants_access(): void
    {
        $this->monetizationEnabled();
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $reader = $this->createReader();

        $plan = \App\Models\SubscriptionPlan::create([
            'name' => 'Premium', 'country_code' => 'US', 'currency' => 'USD',
            'price' => 10, 'billing_interval' => 'month', 'is_active' => true,
        ]);

        $reader->subscriptions()->create([
            'plan_id' => $plan->id,
            'gateway' => 'stripe',
            'locked_price' => 10,
            'locked_currency' => 'USD',
            'status' => 'active',
            'current_period_end' => now()->addMonth(),
        ]);

        $this->getJson("/api/v1/stories/{$story->slug}/episodes/6", $this->bearerFor($reader))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'premium_required');
    }

    public function test_reward_granted_bonus_access_gives_unlimited_access(): void
    {
        $this->monetizationEnabled();
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $reader = $this->createReader(['premium_access_until' => now()->addDays(3)]);

        $this->getJson("/api/v1/stories/{$story->slug}/episodes/6", $this->bearerFor($reader))->assertStatus(200);
    }

    public function test_expired_bonus_access_does_not_grant_access(): void
    {
        $this->monetizationEnabled();
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $reader = $this->createReader(['premium_access_until' => now()->subDay()]);

        $this->getJson("/api/v1/stories/{$story->slug}/episodes/6", $this->bearerFor($reader))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'premium_required');
    }

    public function test_purchasing_a_story_gives_unlimited_access_to_it(): void
    {
        $this->monetizationEnabled();
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $reader = $this->createReader();

        $story->prices()->create(['currency' => 'USD', 'amount' => 9.99]);
        $story->purchases()->create([
            'user_id' => $reader->id, 'gateway' => 'stripe', 'gateway_reference' => 'ref-1',
            'amount' => 9.99, 'currency' => 'USD', 'status' => 'success',
        ]);

        $this->getJson("/api/v1/stories/{$story->slug}/episodes/6", $this->bearerFor($reader))->assertStatus(200);
    }

    public function test_bootstrap_phase_grants_full_access_to_guests(): void
    {
        $this->bootstrapPhase();
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);

        $this->getJson("/api/v1/stories/{$story->slug}/episodes/6")->assertStatus(200);
    }

    public function test_reader_with_prior_progress_keeps_full_access_after_monetization_turns_on(): void
    {
        $this->monetizationEnabled(hasPriorAccess: true);
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $reader = $this->createReader();

        // Grandfathered — despite monetization being on, hasPriorAccess() is true.
        $this->getJson("/api/v1/stories/{$story->slug}/episodes/6", $this->bearerFor($reader))->assertStatus(200);
    }
}

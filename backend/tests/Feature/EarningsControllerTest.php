<?php

namespace Tests\Feature;

use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class EarningsControllerTest extends TestCase
{
    use CreatesStoryFixtures;

    public function test_reports_the_authors_exact_share_of_their_own_story_sales(): void
    {
        config(['payouts.author_share_percentage' => 0.70]);

        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $author = $story->penName->user;
        $buyer = $this->createReader();

        $story->purchases()->create([
            'user_id' => $buyer->id, 'gateway' => 'stripe', 'gateway_reference' => 'ref-1',
            'amount' => 10, 'currency' => 'USD', 'status' => 'success',
        ]);

        $response = $this->getJson('/api/v1/admin/earnings', $this->bearerFor($author));

        $response->assertStatus(200)
            ->assertJsonPath('data.author_share_percent', 70.0)
            ->assertJsonPath('data.gross_story_sales_by_currency.USD', '10.00')
            ->assertJsonPath('data.your_earnings_by_currency.USD', 7.0);
    }

    public function test_does_not_count_a_pending_or_failed_purchase(): void
    {
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $author = $story->penName->user;
        $buyer = $this->createReader();

        $story->purchases()->create([
            'user_id' => $buyer->id, 'gateway' => 'stripe', 'gateway_reference' => 'ref-pending',
            'amount' => 10, 'currency' => 'USD', 'status' => 'pending',
        ]);

        $this->getJson('/api/v1/admin/earnings', $this->bearerFor($author))
            ->assertStatus(200)
            ->assertJsonMissingPath('data.gross_story_sales_by_currency.USD');
    }

    public function test_does_not_count_another_authors_story_sales(): void
    {
        ['story' => $storyA] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        ['story' => $storyB] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $authorA = $storyA->penName->user;
        $buyer = $this->createReader();

        $storyB->purchases()->create([
            'user_id' => $buyer->id, 'gateway' => 'stripe', 'gateway_reference' => 'ref-b',
            'amount' => 25, 'currency' => 'USD', 'status' => 'success',
        ]);

        $this->getJson('/api/v1/admin/earnings', $this->bearerFor($authorA))
            ->assertStatus(200)
            ->assertJsonMissingPath('data.gross_story_sales_by_currency.USD');
    }
}

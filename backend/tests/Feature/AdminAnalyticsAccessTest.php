<?php

namespace Tests\Feature;

use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class AdminAnalyticsAccessTest extends TestCase
{
    use CreatesStoryFixtures;

    public function test_guest_cannot_reach_admin_analytics(): void
    {
        $this->getJson('/api/v1/admin/analytics/overview')->assertStatus(401);
    }

    public function test_reader_is_forbidden_from_admin_analytics(): void
    {
        $reader = $this->createReader();

        $this->getJson('/api/v1/admin/analytics/overview', $this->bearerFor($reader))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_author_is_forbidden_from_admin_analytics(): void
    {
        $author = $this->createAuthor();

        $this->getJson('/api/v1/admin/analytics/overview', $this->bearerFor($author))
            ->assertStatus(403);
    }

    public function test_admin_can_reach_admin_analytics(): void
    {
        $admin = $this->createAdmin();

        $this->getJson('/api/v1/admin/analytics/overview', $this->bearerFor($admin))
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['totals', 'last_7_days', 'last_30_days', 'revenue_by_currency']]);
    }

    public function test_admin_timeseries_endpoint_zero_fills_empty_days(): void
    {
        $admin = $this->createAdmin();

        $response = $this->getJson('/api/v1/admin/analytics/timeseries?metric=reads&days=7', $this->bearerFor($admin));

        $response->assertStatus(200);
        $this->assertCount(7, $response->json('data.series'));
        $this->assertSame(0, $response->json('data.series.0.value'));
    }

    public function test_pageview_tracking_is_public_and_does_not_require_auth(): void
    {
        $this->postJson('/api/v1/analytics/pageview', ['path' => '/browse'])->assertStatus(204);
        $this->assertDatabaseHas('page_views', ['path' => '/browse']);
    }
}

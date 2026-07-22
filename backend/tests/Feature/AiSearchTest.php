<?php

namespace Tests\Feature;

use App\Services\PlatformMetricsService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class AiSearchTest extends TestCase
{
    use CreatesStoryFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(PlatformMetricsService::class, function ($mock) {
            $mock->shouldReceive('isMonetizationEnabled')->andReturn(false);
        });
    }

    public function test_ai_search_falls_back_to_native_results_when_openai_key_is_not_configured(): void
    {
        config(['services.openai.api_key' => null]);
        ['story' => $story] = $this->createStoryWithEpisodes(['title' => 'The Dragon Queen']);

        $response = $this->postJson('/api/v1/search/ai', ['query' => 'dragon']);

        $response->assertStatus(200)
            ->assertJsonPath('data.source', 'native_fallback')
            ->assertJsonPath('data.stories.0.slug', $story->slug);
    }

    public function test_ai_search_falls_back_when_openai_request_fails(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        Http::fake(['api.openai.com/*' => Http::response([], 500)]);
        ['story' => $story] = $this->createStoryWithEpisodes(['title' => 'The Dragon Queen']);

        $this->postJson('/api/v1/search/ai', ['query' => 'dragon'])
            ->assertStatus(200)
            ->assertJsonPath('data.source', 'native_fallback');
    }

    public function test_ai_search_returns_ranked_results_with_reasons_when_openai_succeeds(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        ['story' => $story] = $this->createStoryWithEpisodes(['title' => 'The Dragon Queen']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'results' => [
                                ['slug' => $story->slug, 'reason' => 'Features dragons and a queen, matching the query.'],
                            ],
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/search/ai', ['query' => 'a story with dragons']);

        $response->assertStatus(200)
            ->assertJsonPath('data.source', 'ai')
            ->assertJsonPath('data.stories.0.slug', $story->slug)
            ->assertJsonPath('data.stories.0.ai_reason', 'Features dragons and a queen, matching the query.');
    }

    public function test_ai_search_logs_an_activity_event_for_signed_in_users(): void
    {
        config(['services.openai.api_key' => null]);
        $reader = $this->createReader();

        $this->postJson('/api/v1/search/ai', ['query' => 'anything'], $this->bearerFor($reader))
            ->assertStatus(200);

        $this->assertDatabaseHas('user_activity_events', [
            'user_id' => $reader->id,
            'event_type' => 'ai_search_used',
        ]);
    }
}

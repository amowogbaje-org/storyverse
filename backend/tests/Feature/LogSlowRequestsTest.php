<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class LogSlowRequestsTest extends TestCase
{
    use CreatesStoryFixtures;

    public function test_a_fast_request_is_not_logged(): void
    {
        config(['logging.slow_request_threshold_ms' => 999_999]);

        Log::shouldReceive('channel')->never();

        $this->getJson('/api/v1/platform-status')->assertStatus(200);
    }

    public function test_a_request_over_the_threshold_is_logged_with_route_duration_and_query_count(): void
    {
        // 0 = every request "exceeds" the threshold, without needing an
        // actual sleep() in the test to force real slowness.
        config(['logging.slow_request_threshold_ms' => 0]);

        $logger = \Mockery::mock();
        $logger->shouldReceive('warning')->once()->with('slow request', \Mockery::on(function ($context) {
            return $context['method'] === 'GET'
                && str_contains($context['path'], 'platform-status')
                && $context['status'] === 200
                && is_int($context['ms'])
                && is_int($context['query_count']);
        }));
        Log::shouldReceive('channel')->once()->with('performance')->andReturn($logger);

        $this->getJson('/api/v1/platform-status')->assertStatus(200);
    }
}

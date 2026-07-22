<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Services\PlatformMetricsService;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class PlatformMetricsTest extends TestCase
{
    use CreatesStoryFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_monetization_is_off_by_default_with_no_activity(): void
    {
        $this->assertFalse(app(PlatformMetricsService::class)->isMonetizationEnabled());
    }

    public function test_persisted_switch_short_circuits_the_threshold_check(): void
    {
        // Once flipped, it stays on even with zero reading_progress rows —
        // the whole point is that it can't flip back off during a quiet week.
        PlatformSetting::set('monetization_enabled_at', now()->toISOString());

        $this->assertTrue(app(PlatformMetricsService::class)->isMonetizationEnabled());
    }

    public function test_has_prior_access_is_true_once_a_reader_has_any_progress_on_a_story(): void
    {
        ['story' => $story, 'episodes' => $episodes] = $this->createStoryWithEpisodes();
        $reader = $this->createReader();

        $metrics = app(PlatformMetricsService::class);
        $this->assertFalse($metrics->hasPriorAccess($story, $reader));

        \App\Models\ReadingProgress::create([
            'user_id' => $reader->id,
            'episode_id' => $episodes->first()->id,
            'story_id' => $story->id,
            'progress_percent' => 40,
            'last_read_at' => now(),
        ]);

        $this->assertTrue($metrics->hasPriorAccess($story, $reader));
    }

    public function test_status_reports_current_counts_against_thresholds(): void
    {
        $status = app(PlatformMetricsService::class)->status();

        $this->assertSame(PlatformMetricsService::READS_THRESHOLD, $status['reads_threshold']);
        $this->assertSame(PlatformMetricsService::COMPLETED_READS_THRESHOLD, $status['completed_reads_threshold']);
        $this->assertSame(0, $status['reads']);
        $this->assertFalse($status['monetization_enabled']);
    }
}

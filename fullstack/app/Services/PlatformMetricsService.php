<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\ReadingProgress;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Implements the bootstrap payment strategy from the project brief:
 * everything is free while the platform is small, and subscriptions only
 * start gating access once the site has real traction — 10,000 total
 * episode reads AND 8,000 completed reads. Below that, every story is
 * fully open to everyone, guests included.
 */
class PlatformMetricsService
{
    public const READS_THRESHOLD = 10_000;
    public const COMPLETED_READS_THRESHOLD = 8_000;

    private const SETTING_KEY = 'monetization_enabled_at';
    private const CACHE_KEY = 'platform_metrics:monetization_enabled';
    private const CACHE_TTL = 300; // 5 min — this check runs on every episode/story request,
    // so it's cached rather than counting reading_progress rows on every hit.

    public function isMonetizationEnabled(): bool
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            if (PlatformSetting::get(self::SETTING_KEY)) {
                return true;
            }

            $crossed = $this->totalReads() >= self::READS_THRESHOLD
                && $this->totalCompletedReads() >= self::COMPLETED_READS_THRESHOLD;

            if ($crossed) {
                // One-way switch: once crossed, stays on permanently. Otherwise a quiet
                // week could flip access back open for stories people already paid to read.
                PlatformSetting::set(self::SETTING_KEY, now()->toISOString());
            }

            return $crossed;
        });
    }

    public function totalReads(): int
    {
        return ReadingProgress::count();
    }

    public function totalCompletedReads(): int
    {
        return ReadingProgress::whereNotNull('completed_at')->count();
    }

    /**
     * Progress toward the launch thresholds, for an admin dashboard or a
     * "counting down to subscriptions" banner.
     */
    public function status(): array
    {
        $reads = $this->totalReads();
        $completed = $this->totalCompletedReads();

        return [
            'monetization_enabled' => $this->isMonetizationEnabled(),
            'reads' => $reads,
            'reads_threshold' => self::READS_THRESHOLD,
            'completed_reads' => $completed,
            'completed_reads_threshold' => self::COMPLETED_READS_THRESHOLD,
            // The frontend uses these to decide which episodes to show as
            // locked without a round trip per episode - this is that single
            // source of truth, so a GUEST_EPISODE_LIMIT/
            // REGISTERED_PREMIUM_EPISODE_LIMIT change in .env takes effect
            // for the UI too, not just API enforcement in StoryAccessService.
            'guest_episode_limit' => (int) config('access.guest_episode_limit'),
            'registered_premium_episode_limit' => (int) config('access.registered_premium_episode_limit'),
        ];
    }

    /**
     * "If a user has already read a book, a subscription should not block
     * access to it." Grandfathers anyone with any reading history on this
     * story — so a reader who was three episodes into a premium story before
     * the paywall switched on never gets locked out mid-read.
     */
    public function hasPriorAccess(Story $story, User $user): bool
    {
        return ReadingProgress::where('user_id', $user->id)
            ->where('story_id', $story->id)
            ->exists();
    }
}

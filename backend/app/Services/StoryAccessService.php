<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Story;
use App\Models\User;

class StoryAccessService
{
    public const GUEST_LIMIT = 2;
    private const REGISTERED_PREMIUM_LIMIT = 5;

    public function __construct(private PlatformMetricsService $metrics) {}

    /**
     * Returns null = unlimited access, or an int = max episode_number accessible.
     */
    public function accessibleEpisodeLimit(Story $story, ?User $user): ?int
    {
        if ($user && $user->hasActivePremiumSubscription()) {
            return null;
        }

        // Bootstrap phase: below the platform-wide read thresholds, every story is
        // fully open to everyone, guests included. See PlatformMetricsService.
        if (! $this->metrics->isMonetizationEnabled()) {
            return null;
        }

        // Grandfathering: a signed-in reader who already has reading history on this
        // story keeps full access even after monetization switches on mid-read.
        if ($user && $this->metrics->hasPriorAccess($story, $user)) {
            return null;
        }

        if (! $user) {
            return self::GUEST_LIMIT;
        }

        return $story->access_type === 'free' ? null : self::REGISTERED_PREMIUM_LIMIT;
    }

    public function canAccessEpisode(Story $story, Episode $episode, ?User $user): bool
    {
        $limit = $this->accessibleEpisodeLimit($story, $user);

        return $limit === null || $episode->episode_number <= $limit;
    }

    /**
     * Reason code used by the frontend to decide which upsell to show:
     * 'guest_limit' -> prompt to register, 'premium_required' -> prompt to subscribe.
     */
    public function lockReason(Story $story, Episode $episode, ?User $user): ?string
    {
        if ($this->canAccessEpisode($story, $episode, $user)) {
            return null;
        }

        return $user ? 'premium_required' : 'guest_limit';
    }
}

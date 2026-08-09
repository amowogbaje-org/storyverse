<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Story;
use App\Models\User;

class StoryAccessService
{
    public function __construct(private PlatformMetricsService $metrics, private StoryPurchaseService $purchases) {}

    public function guestLimit(): int
    {
        return (int) config('access.guest_episode_limit');
    }

    private function registeredPremiumLimit(): int
    {
        return (int) config('access.registered_premium_episode_limit');
    }

    /**
     * Returns null = unlimited access, or an int = max episode_number accessible.
     */
    public function accessibleEpisodeLimit(Story $story, ?User $user): ?int
    {
        // Reward-granted access (badges, referral milestones - see
        // BonusAccessService), independent of any purchase.
        if ($user && $user->hasBonusPremiumAccess()) {
            return null;
        }

        // A direct "buy this book" purchase always wins - it's a permanent,
        // one-off unlock independent of subscription status, monetization
        // rollout state, or anything else below.
        if ($user && $story->isPurchasable() && $this->purchases->hasPurchased($user, $story)) {
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
            return $this->guestLimit();
        }

        return $story->access_type === 'free' ? null : $this->registeredPremiumLimit();
    }

    public function canAccessEpisode(Story $story, Episode $episode, ?User $user): bool
    {
        return $this->episodeIsWithinLimit($this->accessibleEpisodeLimit($story, $user), $episode);
    }

    private function episodeIsWithinLimit(?int $limit, Episode $episode): bool
    {
        return $limit === null || $episode->episode_number <= $limit;
    }

    /**
     * Reason code used by the frontend to decide which upsell to show:
     * 'guest_limit' -> prompt to register, 'premium_required' -> prompt to buy the story.
     */
    public function lockReason(Story $story, Episode $episode, ?User $user): ?string
    {
        return $this->lockReasonForLimit($this->accessibleEpisodeLimit($story, $user), $episode, $user);
    }

    /**
     * Same as lockReason(), but takes an already-computed limit instead of
     * looking it up itself - use this in any loop over a story's episodes
     * (see StoryController::show()). accessibleEpisodeLimit() runs several
     * real queries (a purchase lookup, a prior-reading-history check, none
     * of them cached), so calling lockReason() once per episode in a list
     * turns a single page load into N extra query round-trips for an
     * N-episode story. Compute the limit once, then call this per episode -
     * episode_number <= $limit is just an integer comparison, no query at all.
     */
    public function lockReasonForLimit(?int $limit, Episode $episode, ?User $user): ?string
    {
        if ($this->episodeIsWithinLimit($limit, $episode)) {
            return null;
        }

        return $user ? 'premium_required' : 'guest_limit';
    }
}

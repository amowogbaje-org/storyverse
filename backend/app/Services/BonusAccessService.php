<?php

namespace App\Services;

use App\Models\User;

class BonusAccessService
{
    /**
     * Grants $days of premium access, free, independent of any purchase -
     * extends the user's existing bonus window if they have one still
     * running, otherwise starts a fresh one from today. Shared by badge
     * rewards (AwardBadgesListener, gated behind config('badges.rewards_enabled'))
     * and referral milestones (ReferralService, always on).
     *
     * Used to extend/create a Subscription row instead, back when an active
     * subscription was how premium access worked at all. Now that stories
     * are bought individually (see story_prices), this just sets a plain
     * expiry timestamp on the user - see User::hasBonusPremiumAccess() and
     * StoryAccessService, which is the only thing that reads it.
     */
    public function grant(User $user, int $days): void
    {
        if ($days <= 0) {
            return;
        }

        $from = $user->premium_access_until?->isFuture() ? $user->premium_access_until : now();

        $user->update(['premium_access_until' => $from->addDays($days)]);
    }
}

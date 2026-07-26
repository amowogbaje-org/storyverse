<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;

class BonusAccessService
{
    /**
     * Extends an active subscription, or grants a standalone time-boxed one if
     * the user doesn't have one - either way, $days of premium access, free.
     * Shared by badge rewards (AwardBadgesListener, gated behind
     * config('badges.rewards_enabled')) and referral milestones
     * (ReferralService, always on).
     */
    public function grant(User $user, int $days): void
    {
        if ($days <= 0) {
            return;
        }

        $active = $user->subscriptions()->where('status', 'active')->first();

        if ($active) {
            $active->update(['current_period_end' => $active->current_period_end->addDays($days)]);

            return;
        }

        // No active subscription - grant a standalone time-boxed one. Uses the user's
        // resolved plan for currency bookkeeping, priced at 0 since this is a reward,
        // not a purchase; no Payment row is created for it.
        $plan = SubscriptionPlan::where('country_code', $user->country_code)->first()
            ?? SubscriptionPlan::where('country_code', 'US')->first();

        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan?->id,
            'gateway' => 'flutterwave', // placeholder - this subscription was never actually paid for via a gateway
            'gateway_subscription_id' => null,
            'locked_price' => 0,
            'locked_currency' => $plan?->currency ?? 'USD',
            'status' => 'active',
            'current_period_end' => now()->addDays($days),
        ]);
    }
}

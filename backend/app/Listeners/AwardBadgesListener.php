<?php

namespace App\Listeners;

use App\Events\UserActivityLogged;
use App\Models\Badge;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\BadgeUnlocked;
use App\Services\BadgeMetricResolver;
use Illuminate\Contracts\Queue\ShouldQueue;

class AwardBadgesListener implements ShouldQueue
{
    public function __construct(private BadgeMetricResolver $resolver) {}

    public function handle(UserActivityLogged $event): void
    {
        $user = User::find($event->userId);

        if (! $user) {
            return;
        }

        $earnedBadgeIds = $user->badges()->pluck('badge_id');

        // Only re-check badges the user hasn't already earned, and only ones whose
        // criteria_type this resolver can currently compute (see BadgeMetricResolver
        // docblock for what's still a TODO).
        $candidates = Badge::whereNotIn('id', $earnedBadgeIds)->get();

        foreach ($candidates as $badge) {
            $value = $this->resolver->resolve($user, $badge->criteria_type);

            if ($value === null || $value < $badge->criteria_value) {
                continue;
            }

            $this->award($user, $badge);
        }
    }

    private function award(User $user, Badge $badge): void
    {
        $user->badges()->create([
            'badge_id' => $badge->id,
            'earned_at' => now(),
        ]);

        if ($badge->reward_type === 'bonus_access') {
            $this->grantBonusAccess($user, $badge->reward_payload['free_premium_days'] ?? 0);
        }

        $user->notify(new BadgeUnlocked($badge));
    }

    private function grantBonusAccess(User $user, int $days): void
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

<?php

namespace App\Listeners;

use App\Events\UserActivityLogged;
use App\Models\Badge;
use App\Models\User;
use App\Notifications\BadgeUnlocked;
use App\Services\BadgeMetricResolver;
use App\Services\BonusAccessService;
use Illuminate\Contracts\Queue\ShouldQueue;

class AwardBadgesListener implements ShouldQueue
{
    public function __construct(
        private BadgeMetricResolver $resolver,
        private BonusAccessService $bonusAccess,
    ) {}

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

        // Kill switch for now (config/badges.php) - the badge still unlocks and
        // still notifies the user, this just skips actually granting the reward
        // until rewards are turned on. Flip BADGE_REWARDS_ENABLED when ready;
        // nothing else about this flow needs to change.
        if (config('badges.rewards_enabled') && $badge->reward_type === 'bonus_access') {
            $this->bonusAccess->grant($user, $badge->reward_payload['free_premium_days'] ?? 0);
        }

        $user->notify(new BadgeUnlocked($badge));
    }
}

<?php

namespace App\Services;

use App\Events\UserActivityLogged;
use App\Models\User;
use App\Notifications\ReferralMilestoneReached;

class ReferralService
{
    private const VERIFIED_REFERRALS_PER_TIER = 10;
    private const FREE_DAYS_PER_TIER = 30;

    public function __construct(private BonusAccessService $bonusAccess) {}

    /**
     * Called once, at signup, from whichever path created the account
     * (register(), verifyOtp()'s passwordless-create branch, googleAuth()).
     * A no-op if there's no code, the code doesn't resolve to a real user, the
     * code is the new user's own (self-referral), or this user is already
     * attributed to someone (re-registration flows shouldn't re-attribute).
     */
    public function attribute(User $newUser, ?string $referralCode): void
    {
        if (! $referralCode || $newUser->referred_by) {
            return;
        }

        $referrer = User::where('referral_code', $referralCode)->first();

        if (! $referrer || $referrer->id === $newUser->id) {
            return;
        }

        $newUser->update(['referred_by' => $referrer->id]);
    }

    /**
     * Called the moment a referred user actually verifies (email OTP, or
     * immediately at creation for Google sign-in) - referrals only count once
     * verified, per the product rule ("refer 10 *verified* users"). Logs an
     * activity event (feeds BadgeMetricResolver's referrals_verified metric)
     * and, every time the verified count crosses a new multiple of 10, grants
     * another free month - repeating, not one-time, so referring 20, 30, 40
     * people keeps paying off rather than only the first 10 mattering.
     */
    public function onReferredUserVerified(User $verifiedUser): void
    {
        if (! $verifiedUser->referred_by) {
            return;
        }

        $referrer = User::find($verifiedUser->referred_by);

        if (! $referrer) {
            return;
        }

        UserActivityLogged::dispatch($referrer->id, 'referral_verified', [
            'referred_user_id' => $verifiedUser->id,
        ]);

        $verifiedCount = $referrer->referrals()->whereNotNull('email_verified_at')->count();
        $tier = intdiv($verifiedCount, self::VERIFIED_REFERRALS_PER_TIER);

        if ($tier <= $referrer->referral_reward_tier_claimed) {
            return;
        }

        $newTiers = $tier - $referrer->referral_reward_tier_claimed;
        $daysGranted = $newTiers * self::FREE_DAYS_PER_TIER;

        $this->bonusAccess->grant($referrer, $daysGranted);
        $referrer->update(['referral_reward_tier_claimed' => $tier]);
        $referrer->notify(new ReferralMilestoneReached($verifiedCount, $daysGranted));
    }
}

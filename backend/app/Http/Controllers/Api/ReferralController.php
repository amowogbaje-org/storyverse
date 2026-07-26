<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    // Keep in sync with ReferralService::VERIFIED_REFERRALS_PER_TIER - this is
    // just the display-side copy of the same number, not the source of truth.
    private const PER_TIER = 10;

    public function mine(Request $request)
    {
        $user = $this->requireUser($request);

        $verifiedCount = $user->referrals()->whereNotNull('email_verified_at')->count();
        $pendingCount = $user->referrals()->whereNull('email_verified_at')->count();

        return $this->ok([
            'referral_code' => $user->referral_code,
            'referral_link' => rtrim(config('app.frontend_url'), '/')."/register?ref={$user->referral_code}",
            'verified_count' => $verifiedCount,
            'pending_count' => $pendingCount,
            'per_tier' => self::PER_TIER,
            'tiers_claimed' => $user->referral_reward_tier_claimed,
            // How many more verified referrals until the next free month - always
            // 1-10, never 0, even right after just having claimed a tier (that's
            // the start of the next one, not the end of this one).
            'remaining_to_next_reward' => self::PER_TIER - ($verifiedCount % self::PER_TIER),
            'referrals' => $user->referrals()->orderByDesc('created_at')->get()->map(fn ($r) => [
                'name' => $r->display_name,
                'verified' => $r->email_verified_at !== null,
                'joined_at' => $r->created_at,
            ]),
        ]);
    }
}

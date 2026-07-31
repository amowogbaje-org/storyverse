<?php

return [
    // Off by default, same reasoning as config/badges.php's rewards toggle:
    // real money movement is not something to turn on silently. While off,
    // GenerateMonthlyPayouts still creates the payout records (so you can see
    // what would be owed) but never calls a gateway's transfer API - payouts
    // stay 'pending' for an admin to mark paid manually (e.g. after doing the
    // bank transfer themselves) via the admin Payouts page.
    'auto_send_enabled' => env('PAYOUTS_AUTO_SEND_ENABLED', false),

    // Skip generating a payout below this amount (per currency) - not worth a
    // bank transfer's fees/effort for a few cents/kobo. The unpaid amount is
    // simply not recorded as a payout that month; it isn't carried forward
    // and added to next month's total (see GenerateMonthlyPayouts) - revisit
    // this if authors below the threshold need their small amounts to
    // eventually accumulate into a payable one instead of just being skipped.
    'minimum_payout_amount' => [
        'USD' => (float) env('PAYOUTS_MINIMUM_USD', 5),
        'GBP' => (float) env('PAYOUTS_MINIMUM_GBP', 5),
        'NGN' => (float) env('PAYOUTS_MINIMUM_NGN', 2000),
    ],
];

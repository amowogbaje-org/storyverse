<?php

return [
    'secret' => env('JWT_SECRET'),
    // Short-lived on purpose now that a real refresh token exists
    // (RefreshTokenService) to keep the reader signed in over the following
    // 30 days - this access token no longer has to carry that weight itself.
    // Reverted from the temporary 30-day value used before the refresh
    // token existed.
    'ttl_minutes' => env('JWT_TTL', 60),
    // How long a refresh token stays redeemable before the reader has to
    // sign in again outright. This is the number that actually controls
    // "stay logged in for N days".
    'refresh_ttl_days' => env('JWT_REFRESH_TTL_DAYS', 30),
];

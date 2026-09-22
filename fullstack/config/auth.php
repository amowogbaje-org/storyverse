<?php

return [
    'defaults' => [
        'guard' => 'web',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    // No 'passwords' broker/table here on purpose - password reset goes
    // through the same OTP-by-email flow as email verification
    // (AuthController::forgotPassword/resetPassword), not Laravel's default
    // password_reset_tokens table, which was never migrated.
    'password_timeout' => 10800,
];

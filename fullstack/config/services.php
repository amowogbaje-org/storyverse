<?php

return [
    'flutterwave' => [
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'webhook_hash' => env('FLUTTERWAVE_WEBHOOK_HASH'),
    ],

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'google' => [
        // OAuth client ID for "Sign in with Google". Only needed if/when the
        // Blade login page grows a Google button - not wired up yet.
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'analytics' => [
        'summary_emails' => env('ANALYTICS_SUMMARY_EMAILS', ''),
    ],
];

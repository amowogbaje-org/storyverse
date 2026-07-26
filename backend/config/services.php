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

    'sms_termii' => [
        'api_key' => env('SMS_TERMII_API_KEY'),
    ],

    // Superseded by config/ai.php (published by the Laravel AI SDK, which reads
    // OPENAI_API_KEY directly) now that AiSearchService goes through
    // App\Ai\Agents\StorySearchAgent instead of calling OpenAI's API directly.
    // Left here in case anything else in the app starts talking to OpenAI's raw
    // API in the future.
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'google' => [
        // OAuth client ID for "Sign in with Google" (Google Identity Services).
        // Must match the audience ("aud") claim on the ID token the frontend sends us.
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'analytics' => [
        // Comma-separated extra recipients for the daily analytics summary email
        // (app:send-analytics-summary), on top of every role=admin user.
        'summary_emails' => env('ANALYTICS_SUMMARY_EMAILS', ''),
    ],

    'ai_search' => [
        // Which Laravel AI SDK provider (Laravel\Ai\Enums\Lab case, lowercase
        // string is fine) StorySearchAgent uses. Switching providers - e.g. from
        // Gemini to OpenAI once you have a key for it - is just this env var
        // plus that provider's own API key in config/ai.php; no code change.
        'provider' => env('AI_SEARCH_PROVIDER', 'gemini'),
    ],
];

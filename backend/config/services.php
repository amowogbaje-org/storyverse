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

    // See App\Http\Middleware\TelescopeAccessKey - Telescope's own gate
    // needs a session-authenticated web-guard user, which this JWT-only app
    // never has. Generate a real secret, don't leave this blank:
    //   openssl rand -hex 32
    'telescope_access_key' => env('TELESCOPE_ACCESS_KEY'),

    'ai_search' => [
        // Which Laravel AI SDK provider (Laravel\Ai\Enums\Lab case, lowercase
        // string is fine) StorySearchAgent uses. Switching providers - e.g. from
        // Gemini to OpenAI once you have a key for it - is just this env var
        // plus that provider's own API key in config/ai.php; no code change.
        'provider' => env('AI_SEARCH_PROVIDER', 'gemini'),
    ],

    'episode_styling' => [
        // Which Laravel AI SDK provider EpisodeStylingAgent uses - gemini,
        // ollama, and deepseek all already have driver config in config/ai.php
        // (just add that provider's key/URL there), so switching is only this
        // env var, never a code change. Defaults to gemini since it's the one
        // already wired up for AI search above.
        'provider' => env('EPISODE_STYLING_PROVIDER', 'gemini'),

        // How many unstyled episodes StyleEpisodes sends to the agent per run
        // (scheduled every 15 minutes - see routes/console.php). Keep this
        // small: it bounds both API cost per run and how long one run takes.
        'batch_size' => env('EPISODE_STYLING_BATCH_SIZE', 10),

        // After this many consecutive failures on the same episode, stop
        // retrying and fall back to showing the reader the raw text as-is
        // rather than silently retrying forever every 15 minutes.
        'max_attempts' => env('EPISODE_STYLING_MAX_ATTEMPTS', 5),
    ],
];

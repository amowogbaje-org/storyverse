<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'up'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,https://storyverse.amowogbaje.com')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    // Content-Disposition needs to be explicitly exposed - browsers don't
    // surface it to JS on a cross-origin response otherwise, which the admin
    // panel's Telescope export needs to read the real filename off (see
    // downloadTelescopeExport in useAdmin.js).
    'exposed_headers' => ['Content-Disposition'],
    'max_age' => 0,
    'supports_credentials' => true,
];

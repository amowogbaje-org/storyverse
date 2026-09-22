<?php

return [
    // 'file' on purpose: cPanel gives every app its own writable storage/
    // folder with no extra setup, and a sessions DB table is one more thing
    // to migrate/maintain for no real benefit at this scale.
    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => (int) env('SESSION_LIFETIME', 60 * 24 * 14), // 14 days - readers shouldn't get logged out mid-book
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => storage_path('framework/sessions'),
    'connection' => null,
    'table' => 'sessions',
    'store' => null,
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', 'storyverse_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE'),
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
];

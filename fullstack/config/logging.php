<?php

use Monolog\Handler\StreamHandler;

return [
    'default' => env('LOG_CHANNEL', 'daily'),
    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    // See App\Http\Middleware\LogSlowRequests.
    'slow_request_threshold_ms' => (int) env('SLOW_REQUEST_THRESHOLD_MS', 1000),
    // See App\Providers\AppServiceProvider::logSlowQueries().
    'slow_query_threshold_ms' => (int) env('SLOW_QUERY_THRESHOLD_MS', 200),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily'],
            'ignore_exceptions' => false,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 14,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'info'),
        ],

        // Its own file (`tail -f storage/logs/performance.log`), not
        // interleaved with everything else in laravel.log.
        'performance' => [
            'driver' => 'daily',
            'path' => storage_path('logs/performance.log'),
            'level' => 'warning',
            'days' => 14,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => \Monolog\Handler\NullHandler::class,
        ],
    ],
];

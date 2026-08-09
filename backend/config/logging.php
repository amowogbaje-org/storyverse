<?php

use Monolog\Handler\StreamHandler;

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    // Read by AppServiceProvider::logSlowQueries() and
    // App\Http\Middleware\LogSlowRequests - a single knob for "how slow is
    // slow" for both. 200ms/1000ms are reasonable starting points; tighten
    // once you've seen a week of real traffic and know your actual baseline.
    'slow_query_threshold_ms' => (int) env('SLOW_QUERY_THRESHOLD_MS', 200),
    'slow_request_threshold_ms' => (int) env('SLOW_REQUEST_THRESHOLD_MS', 1000),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],
        // Separate file from the main app log on purpose - slow-query/slow-
        // request entries are operational noise you want to grep on their
        // own (`tail -f storage/logs/performance.log`), not interleaved with
        // application errors. Daily rotation keeps any one file bounded;
        // 14 days is enough to spot a trend without unbounded disk growth.
        'performance' => [
            'driver' => 'daily',
            'path' => storage_path('logs/performance.log'),
            'level' => 'debug',
            'days' => 14,
        ],
        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => ['stream' => 'php://stderr'],
        ],
        'null' => [
            'driver' => 'monolog',
            'handler' => \Monolog\Handler\NullHandler::class,
        ],
    ],
];

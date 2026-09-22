<?php

return [
    // Same reasoning as the old backend's DEPLOYMENT.md: shared cPanel
    // hosting can't run a long-lived `queue:work` process, so queued work
    // (right now, just the badge-award listener once badges ship here) runs
    // inline during the request instead. Zero background processes to
    // babysit.
    'default' => env('QUEUE_CONNECTION', 'sync'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
    ],

    'failed' => [
        'driver' => 'database-uuids',
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];

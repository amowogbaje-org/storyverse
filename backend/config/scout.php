<?php

// Inert until `composer require laravel/scout meilisearch/meilisearch-php` is run -
// removed from the default require so a bare-metal/cPanel deploy doesn't need
// Meilisearch running anywhere. Native search (ILIKE/LIKE, see SearchController)
// works standalone without this. Kept as a config reference for later: add the
// Laravel\Scout\Searchable trait to Story once you're ready to switch search
// engines, then reinstall the two packages above.
return [
    'driver' => env('SCOUT_DRIVER', 'meilisearch'),
    'prefix' => env('SCOUT_PREFIX', 'storyverse_'),
    'queue' => env('SCOUT_QUEUE', true),
    'after_commit' => true,
    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],
    'soft_delete' => false,

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://meilisearch:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index-settings' => [
            'stories' => [
                'filterableAttributes' => ['category_id', 'status', 'access_type'],
                'sortableAttributes' => ['views_count', 'likes_count', 'published_at'],
            ],
        ],
    ],
];

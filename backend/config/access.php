<?php

return [
    // A story can't be published until it has at least this many published
    // episodes - the goal is a reader landing on a "complete-feeling" series
    // with real depth to get into, not a story that runs out after a
    // handful of chapters.
    'min_episodes_to_publish' => env('MIN_EPISODES_TO_PUBLISH', 60),

    // Free-preview episode limits (see StoryAccessService). Guests always get
    // the smaller number - it's the incentive to create an account at all.
    // Registered readers get more, but only up to registered_premium_episode_limit
    // episodes of a premium story before being asked to subscribe - enough
    // to get genuinely hooked before hitting the paywall.
    'guest_episode_limit' => env('GUEST_EPISODE_LIMIT', 1),
    'registered_premium_episode_limit' => env('REGISTERED_PREMIUM_EPISODE_LIMIT', 5),
];

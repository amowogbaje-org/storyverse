<?php

return [
    // Badges still unlock, show in the UI, and notify the user with rewards
    // off - this only gates actually *granting* a badge's reward (e.g. bonus
    // premium days). Flip to true once you're ready to activate rewards;
    // nothing else needs to change.
    'rewards_enabled' => env('BADGE_REWARDS_ENABLED', false),

    // Thresholds for the "Tastemaker" badge (bookmarked_before_trending):
    // counts as an early/prescient bookmark if the story had this few views
    // or fewer at the moment they bookmarked it...
    'tastemaker_early_views_threshold' => env('BADGE_TASTEMAKER_EARLY_VIEWS', 50),
    // ...and it counts as having since "gone trending" once the story's
    // current views_count reaches this many.
    'tastemaker_trending_views_threshold' => env('BADGE_TASTEMAKER_TRENDING_VIEWS', 1000),
];

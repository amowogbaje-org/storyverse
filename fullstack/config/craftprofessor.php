<?php

return [
    // Max number of episodes' full text sent in a single /api/stories/{slug}/json
    // response - metadata for every episode always goes out regardless, only the
    // (much heavier) `content` field is batched. See
    // CraftProfessorExportController's docblock for why.
    'content_batch_size' => env('CRAFTPROFESSOR_CONTENT_BATCH_SIZE', 40),
];

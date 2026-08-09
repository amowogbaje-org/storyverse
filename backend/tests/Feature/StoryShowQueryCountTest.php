<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class StoryShowQueryCountTest extends TestCase
{
    use CreatesStoryFixtures;

    /**
     * Regression test for a real N+1: StoryController::show() used to call
     * lockReason() once per episode, and lockReason() re-ran
     * accessibleEpisodeLimit() from scratch every time - a purchase lookup
     * plus an uncached prior-reading-history query, per episode. Fixed by
     * computing the limit once and reusing it via lockReasonForLimit(). This
     * doesn't assert an exact query count (too brittle - unrelated changes
     * elsewhere would break it for no real reason); it asserts the query
     * count for a 3-episode story and a 15-episode story are within a small
     * constant of each other, which is what "doesn't scale with episode
     * count" actually means.
     */
    public function test_query_count_does_not_scale_with_episode_count(): void
    {
        $this->monetizationEnabled();
        $reader = $this->createReader();

        ['story' => $smallStory] = $this->createStoryWithEpisodes(['access_type' => 'premium'], episodeCount: 3);
        ['story' => $bigStory] = $this->createStoryWithEpisodes(['access_type' => 'premium'], episodeCount: 15);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson("/api/v1/stories/{$smallStory->slug}", $this->bearerFor($reader))->assertStatus(200);
        $smallQueryCount = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->getJson("/api/v1/stories/{$bigStory->slug}", $this->bearerFor($reader))->assertStatus(200);
        $bigQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 5x the episodes should not mean anywhere close to 5x the queries -
        // a handful extra (view-count row insert, episode rows themselves
        // being N rows either way via a single already-eager-loaded query)
        // is expected; growing roughly linearly with episode count is the
        // regression this test exists to catch.
        $this->assertLessThan(
            $smallQueryCount + 5,
            $bigQueryCount,
            "Query count grew from {$smallQueryCount} to {$bigQueryCount} between a 3-episode and 15-episode story - looks like an N+1 regression."
        );
    }
}

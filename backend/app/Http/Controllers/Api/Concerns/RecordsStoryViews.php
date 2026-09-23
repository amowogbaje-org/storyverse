<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Story;
use App\Models\StoryView;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Extracted out of StoryController so EpisodeController can record the same
 * "story view" when a reader opens an episode directly, without going
 * through a second /stories/{slug} request just to get the view counted.
 * See StoryController::show()'s original docblock (still there) for the
 * full "why per-visitor-per-hour, why not batch the write" reasoning - none
 * of that changed, this is a pure extraction.
 */
trait RecordsStoryViews
{
    private function recordStoryView(Request $request, Story $story, ?User $user): void
    {
        if (! $this->shouldCountView($request, $story, $user)) {
            return;
        }

        StoryView::create([
            'user_id' => $user?->id,
            'story_id' => $story->id,
            'session_hash' => $this->sessionHash($request),
            'viewed_at' => now(),
        ]);
        $story->increment('views_count');
    }

    /**
     * A "view" only counts once per visitor per story per hour, not on every
     * page load/reload/re-render - refreshing the tab five times isn't five
     * reads. Visitor = the logged-in user's id, or (for guests) the same
     * per-browser session id already used for StoryView.session_hash - stable
     * across reloads for one visitor, unlike IP+UA which can collide (shared
     * office/mobile networks) or split (VPN, IP rotation).
     *
     * Shared between StoryController::show() and EpisodeController::show():
     * reading an episode directly (without visiting the story page first)
     * should still count as a story view, and using the same cache key here
     * means the dedup window is shared too - reading 3 episodes of the same
     * story in one hour still only counts once, same as reloading the story
     * page 3 times did before this was extracted.
     *
     * This alone gets you ~95% of what a Redis "seen" key + TTL buys you,
     * using the same portable Cache facade the rest of the app's caching goes
     * through (see HomeCache/EpisodeController) - identical behavior whether
     * CACHE_STORE is redis (Docker/cloud) or file/database (cPanel).
     *
     * Deliberately NOT also batching the DB write itself (accumulate a
     * counter in cache, flush to the `stories` row once a minute via a
     * scheduled job): dedup already cuts writes from "every reload" down to
     * "at most once per visitor per story per hour", which is the actual fix
     * for the reported problem, and it's a single indexed UPDATE + one INSERT
     * per unique view - trivial for Postgres/MySQL at this traffic level.
     * Batching on top would mainly help if writes became the bottleneck at
     * much higher scale, but it trades that for real complexity: an
     * "increment counter, then atomically read-and-reset it" step, which
     * Redis does natively (INCR + GETSET) but file/database cache stores
     * can't guarantee atomically - a flush racing a concurrent increment can
     * lose counts. Worth revisiting if `stories` writes ever show up as a
     * real bottleneck, but not before.
     */
    private function shouldCountView(Request $request, Story $story, ?User $user): bool
    {
        $visitor = $user ? "user:{$user->id}" : 'guest:'.$this->sessionHash($request);
        $key = "story-view-seen:{$story->id}:{$visitor}";

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, true, now()->addHour());

        return true;
    }
}

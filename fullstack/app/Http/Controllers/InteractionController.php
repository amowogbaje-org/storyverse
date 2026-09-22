<?php

namespace App\Http\Controllers;

use App\Models\ReadingProgress;
use App\Models\Story;
use App\Services\StoryAccessService;
use Illuminate\Http\Request;

class InteractionController extends Controller
{
    public function __construct(private StoryAccessService $access) {}

    public function like(Request $request, string $slug)
    {
        $story = Story::where('slug', $slug)->firstOrFail();
        $created = $story->likes()->firstOrCreate(['user_id' => $request->user()->id]);

        if ($created->wasRecentlyCreated) {
            $story->increment('likes_count');
        }

        return $this->interactionResponse($request, ['liked' => true, 'likes_count' => $story->fresh()->likes_count]);
    }

    public function unlike(Request $request, string $slug)
    {
        $story = Story::where('slug', $slug)->firstOrFail();

        if ($story->likes()->where('user_id', $request->user()->id)->delete()) {
            $story->decrement('likes_count');
        }

        return $this->interactionResponse($request, ['liked' => false, 'likes_count' => $story->fresh()->likes_count]);
    }

    public function bookmark(Request $request, string $slug)
    {
        $story = Story::where('slug', $slug)->firstOrFail();
        $created = $story->bookmarks()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['views_count_at_bookmark' => $story->views_count]
        );

        if ($created->wasRecentlyCreated) {
            $story->increment('bookmarks_count');
        }

        return $this->interactionResponse($request, ['bookmarked' => true]);
    }

    public function unbookmark(Request $request, string $slug)
    {
        $story = Story::where('slug', $slug)->firstOrFail();

        if ($story->bookmarks()->where('user_id', $request->user()->id)->delete()) {
            $story->decrement('bookmarks_count');
        }

        return $this->interactionResponse($request, ['bookmarked' => false]);
    }

    /** Called by the reader page's Alpine component as the user scrolls - see resources/js/app.js. */
    public function updateProgress(Request $request, string $slug, int $episodeNumber)
    {
        $data = $request->validate(['percent' => ['required', 'integer', 'min:0', 'max:100']]);

        $story = Story::where('slug', $slug)->firstOrFail();
        $episode = $story->episodes()->where('episode_number', $episodeNumber)->firstOrFail();
        $user = $request->user();

        $limit = $this->access->accessibleEpisodeLimit($story, $user);
        if ($this->access->lockReasonForLimit($limit, $episode, $user) !== null) {
            abort(403, 'This episode is locked.');
        }

        $existing = $episode->readingProgress()->where('user_id', $user->id)->first();

        // Percent only ever goes up - concurrent scroll-triggered requests
        // have no guaranteed order, so a slow earlier request landing after
        // a later 100% one must not roll progress back down.
        $newPercent = $existing ? max($existing->progress_percent, $data['percent']) : $data['percent'];
        $isNowComplete = $newPercent >= 100 || $existing?->completed_at;

        $progress = $episode->readingProgress()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'story_id' => $episode->story_id,
                'progress_percent' => $newPercent,
                'last_read_at' => now(),
                'completed_at' => $isNowComplete ? ($existing?->completed_at ?? now()) : null,
            ]
        );

        return response()->json(['progress_percent' => $progress->progress_percent]);
    }

    /**
     * Alpine hits these as background fetch() calls for instant button
     * feedback (JSON), but a plain HTML form post (no JS) still works via
     * the redirect-back fallback - progressive enhancement, not a
     * JSON-only API.
     */
    private function interactionResponse(Request $request, array $json)
    {
        return $request->wantsJson() ? response()->json($json) : back();
    }
}

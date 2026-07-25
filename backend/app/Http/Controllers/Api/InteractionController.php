<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\UserActivityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InteractionController extends Controller
{
    public function __construct(private \App\Services\StoryAccessService $access) {}

    public function like(Request $request, string $slug)
    {
        $user = $this->requireUser($request);
        $story = Story::where('slug', $slug)->firstOrFail();

        $created = $story->likes()->firstOrCreate(['user_id' => $user->id]);

        if ($created->wasRecentlyCreated) {
            $story->increment('likes_count');
            $this->logActivity($user->id, 'story_liked', ['story_id' => $story->id]);
        }

        return $this->ok(['liked' => true]);
    }

    public function unlike(Request $request, string $slug)
    {
        $user = $this->requireUser($request);
        $story = Story::where('slug', $slug)->firstOrFail();

        $deleted = $story->likes()->where('user_id', $user->id)->delete();

        if ($deleted) {
            $story->decrement('likes_count');
        }

        return $this->ok(['liked' => false]);
    }

    public function bookmark(Request $request, string $slug)
    {
        $user = $this->requireUser($request);
        $story = Story::where('slug', $slug)->firstOrFail();

        $created = $story->bookmarks()->firstOrCreate(['user_id' => $user->id]);

        if ($created->wasRecentlyCreated) {
            $story->increment('bookmarks_count');
            $this->logActivity($user->id, 'story_bookmarked', ['story_id' => $story->id]);
        }

        return $this->ok(['bookmarked' => true]);
    }

    public function unbookmark(Request $request, string $slug)
    {
        $user = $this->requireUser($request);
        $story = Story::where('slug', $slug)->firstOrFail();

        $deleted = $story->bookmarks()->where('user_id', $user->id)->delete();

        if ($deleted) {
            $story->decrement('bookmarks_count');
        }

        return $this->ok(['bookmarked' => false]);
    }

    public function myBookmarks(Request $request)
    {
        $user = $this->requireUser($request);

        $paginator = $user->belongsToMany(Story::class, 'story_bookmarks')
            ->with(['penName', 'category'])
            ->cursorPaginate(20);

        $ids = collect($paginator->items())->pluck('id');
        $progress = \App\Support\StoryCardPresenter::progressMap($user, $ids);
        $liked = \App\Support\StoryCardPresenter::likedMap($user, $ids);
        $bookmarked = \App\Support\StoryCardPresenter::bookmarkedMap($user, $ids);

        return $this->paginated($paginator, fn (Story $s) => \App\Support\StoryCardPresenter::card($s, $user, $progress, $liked, $bookmarked));
    }

    public function myLibrary(Request $request)
    {
        $user = $this->requireUser($request);

        $storyIds = $user->readingProgress()
            ->distinct('story_id')
            ->pluck('story_id');

        $stories = Story::whereIn('id', $storyIds)->with(['penName', 'category'])->get();
        $ids = $stories->pluck('id');
        $progress = \App\Support\StoryCardPresenter::progressMap($user, $ids);
        $liked = \App\Support\StoryCardPresenter::likedMap($user, $ids);
        $bookmarked = \App\Support\StoryCardPresenter::bookmarkedMap($user, $ids);

        return $this->ok($stories->map(fn (Story $s) => \App\Support\StoryCardPresenter::card($s, $user, $progress, $liked, $bookmarked)));
    }

    public function updateProgress(Request $request, string $slug, int $episodeNumber)
    {
        $user = $this->requireUser($request);
        $story = Story::where('slug', $slug)->firstOrFail();
        $episode = $story->episodes()->where('episode_number', $episodeNumber)->firstOrFail();

        if (! $this->access->canAccessEpisode($story, $episode, $user)) {
            return $this->error($this->access->lockReason($story, $episode, $user), 'This episode is locked.', 403);
        }

        $data = $request->validate([
            'percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $existing = $episode->readingProgress()->where('user_id', $user->id)->first();

        // The reader page can fire several progress updates in quick succession
        // (one per scroll tick); network timing gives no guarantee they arrive in
        // order. Without this guard, a slow in-flight request for an earlier
        // (lower) percent can land after a later 100% one and silently overwrite
        // it - completed_at gets cleared and the episode looks "not started"
        // again even though the user finished it. So: percent only ever goes up,
        // and once completed, completed_at is never unset by a later update.
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

        if ($newPercent >= 100 && ! $existing?->completed_at) {
            $this->logActivity($user->id, 'episode_completed', ['episode_id' => $episode->id, 'story_id' => $episode->story_id]);
            $this->checkStoryCompletion($user->id, $episode->story_id);
        }

        return $this->ok($progress);
    }

    private function checkStoryCompletion(int $userId, int $storyId): void
    {
        $story = \App\Models\Story::with('publishedEpisodes')->find($storyId);

        if (! $story || $story->publishedEpisodes->isEmpty()) {
            return;
        }

        $completedCount = \App\Models\ReadingProgress::where('user_id', $userId)
            ->where('story_id', $storyId)
            ->whereNotNull('completed_at')
            ->count();

        if ($completedCount >= $story->publishedEpisodes->count()) {
            $this->logActivity($userId, 'story_completed', [
                'story_id' => $storyId,
                'pen_name_id' => $story->pen_name_id,
            ]);
        }
    }

    public function storyProgress(Request $request, string $slug)
    {
        $user = $this->requireUser($request);
        $story = Story::where('slug', $slug)->firstOrFail();

        $percent = $this->storyProgressPercent($user->id, $story->id);

        // Enforced server-side per the reader UX doc: circle only renders at >= 20%.
        return $this->ok(['progress_percent' => $percent !== null && $percent >= 20 ? $percent : null]);
    }

    private function storyProgressPercent(int $userId, int $storyId): ?float
    {
        $story = \App\Models\Story::with('publishedEpisodes')->find($storyId);
        $episodeCount = $story?->publishedEpisodes->count() ?? 0;

        if ($episodeCount === 0) {
            return null;
        }

        // Sum progress across every touched episode, but divide by the total
        // number of published episodes - not just the ones with a row - so an
        // untouched episode correctly counts as 0% rather than being excluded
        // from the average entirely (which previously overstated completion,
        // e.g. 1 of 5 episodes finished showed as 100% instead of 20%).
        $sum = DB::table('reading_progress')
            ->where('user_id', $userId)
            ->where('story_id', $storyId)
            ->sum('progress_percent');

        if ($sum == 0) {
            return null;
        }

        return round($sum / $episodeCount);
    }

    private function logActivity(int $userId, string $eventType, array $metadata = []): void
    {
        UserActivityEvent::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);

        \App\Events\UserActivityLogged::dispatch($userId, $eventType, $metadata);
    }
}

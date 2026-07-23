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

        $progress = \App\Support\StoryCardPresenter::progressMap($user, collect($paginator->items())->pluck('id'));

        return $this->paginated($paginator, fn (Story $s) => \App\Support\StoryCardPresenter::card($s, $user, $progress));
    }

    public function myLibrary(Request $request)
    {
        $user = $this->requireUser($request);

        $storyIds = $user->readingProgress()
            ->distinct('story_id')
            ->pluck('story_id');

        $stories = Story::whereIn('id', $storyIds)->with(['penName', 'category'])->get();
        $progress = \App\Support\StoryCardPresenter::progressMap($user, $stories->pluck('id'));

        return $this->ok($stories->map(fn (Story $s) => \App\Support\StoryCardPresenter::card($s, $user, $progress)));
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

        $progress = $episode->readingProgress()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'story_id' => $episode->story_id,
                'progress_percent' => $data['percent'],
                'last_read_at' => now(),
                'completed_at' => $data['percent'] >= 100 ? now() : null,
            ]
        );

        if ($data['percent'] >= 100) {
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
        $avg = DB::table('reading_progress')
            ->where('user_id', $userId)
            ->where('story_id', $storyId)
            ->avg('progress_percent');

        return $avg !== null ? round($avg) : null;
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

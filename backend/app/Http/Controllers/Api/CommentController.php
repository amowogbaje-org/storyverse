<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Story;
use App\Models\UserActivityEvent;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request, string $slug)
    {
        $story = Story::where('slug', $slug)->firstOrFail();

        $query = $story->comments()->whereNull('parent_id')->with('user:id,name,avatar_url');

        if ($episodeId = $request->query('episode_id')) {
            $query->where('episode_id', $episodeId);
        }

        $paginator = $query->orderByDesc('created_at')->cursorPaginate(20);

        return $this->paginated($paginator, fn (Comment $c) => $this->commentPayload($c));
    }

    public function store(Request $request, string $slug)
    {
        $user = $this->requireUser($request);
        $story = Story::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'episode_id' => ['nullable', 'exists:episodes,id'],
            'parent_id' => ['nullable', 'exists:comments,id'],
        ]);

        $comment = $story->comments()->create([
            'user_id' => $user->id,
            'episode_id' => $data['episode_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
        ]);

        $story->increment('comments_count');

        UserActivityEvent::create([
            'user_id' => $user->id,
            'event_type' => 'comment_posted',
            'metadata' => ['story_id' => $story->id, 'comment_id' => $comment->id],
            'created_at' => now(),
        ]);

        \App\Events\UserActivityLogged::dispatch($user->id, 'comment_posted', ['story_id' => $story->id, 'comment_id' => $comment->id]);

        return $this->ok($this->commentPayload($comment->load('user:id,name,avatar_url')), 201);
    }

    public function destroy(Request $request, int $id)
    {
        $user = $this->requireUser($request);
        $comment = Comment::findOrFail($id);

        if ($comment->user_id !== $user->id && $user->role !== 'admin') {
            return $this->error('forbidden', 'You cannot delete this comment.', 403);
        }

        $comment->delete();
        $comment->story()->decrement('comments_count');

        return $this->ok(['deleted' => true]);
    }

    private function commentPayload(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'episode_id' => $comment->episode_id,
            'parent_id' => $comment->parent_id,
            'created_at' => $comment->created_at,
            'user_display_name' => $comment->user?->display_name,
            'user_avatar_url' => $comment->user?->avatar_url,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EpisodeManagementController extends Controller
{
    public function index(Request $request, int $storyId)
    {
        $story = $this->ownedStoryOrFail($request, $storyId);

        return $this->ok($story->episodes()->orderBy('episode_number')->get());
    }

    public function store(Request $request, int $storyId)
    {
        $story = $this->ownedStoryOrFail($request, $storyId);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $nextNumber = ($story->episodes()->max('episode_number') ?? 0) + 1;

        $episode = $story->episodes()->create([
            'title' => $data['title'],
            'content' => $data['content'],
            'episode_number' => $nextNumber,
            'word_count' => str_word_count(strip_tags($data['content'])),
            'status' => 'draft',
        ]);

        $story->increment('episodes_count');

        return $this->ok($episode, 201);
    }

    public function update(Request $request, int $storyId, int $episodeId)
    {
        $story = $this->ownedStoryOrFail($request, $storyId);
        $episode = $story->episodes()->findOrFail($episodeId);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
        ]);

        if (isset($data['content'])) {
            $data['word_count'] = str_word_count(strip_tags($data['content']));
        }

        $episode->update($data);

        return $this->ok($episode);
    }

    public function publish(Request $request, int $storyId, int $episodeId)
    {
        $story = $this->ownedStoryOrFail($request, $storyId);
        $episode = $story->episodes()->findOrFail($episodeId);

        $episode->update(['status' => 'published', 'published_at' => $episode->published_at ?? now()]);

        return $this->ok($episode);
    }

    public function destroy(Request $request, int $storyId, int $episodeId)
    {
        $story = $this->ownedStoryOrFail($request, $storyId);
        $episode = $story->episodes()->findOrFail($episodeId);
        $episode->delete();
        $story->decrement('episodes_count');

        return $this->ok(['deleted' => true]);
    }

    private function ownedStoryOrFail(Request $request, int $storyId): Story
    {
        $user = $this->requireUser($request);
        $story = Story::findOrFail($storyId);

        if ($user->role !== 'admin' && ! $user->penNames()->where('id', $story->pen_name_id)->exists()) {
            abort(response()->json(['error' => ['code' => 'forbidden', 'message' => 'You do not own this story.']], 403));
        }

        return $story;
    }
}

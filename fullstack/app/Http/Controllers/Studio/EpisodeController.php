<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EpisodeController as ReaderEpisodeController;
use App\Models\Story;
use Illuminate\Http\Request;

class EpisodeController extends Controller
{
    public function index(Request $request, int $storyId)
    {
        $story = $this->ownedStoryOrFail($request, $storyId);
        $episodes = $story->episodes()->orderBy('episode_number')->get();

        return view('studio.episodes.index', compact('story', 'episodes'));
    }

    public function create(Request $request, int $storyId)
    {
        $story = $this->ownedStoryOrFail($request, $storyId);

        return view('studio.episodes.create', compact('story'));
    }

    public function store(Request $request, int $storyId)
    {
        $story = $this->ownedStoryOrFail($request, $storyId);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $nextNumber = ($story->episodes()->max('episode_number') ?? 0) + 1;

        // No AI styling pass in this app (see README) - content and
        // raw_content are simply the same text; raw_content stays the field
        // CraftProfessor's export always reads.
        $story->episodes()->create([
            'title' => $data['title'],
            'content' => $data['content'],
            'raw_content' => $data['content'],
            'raw_content_updated_at' => now(),
            'styled_at' => now(),
            'styling_attempts' => 0,
            'episode_number' => $nextNumber,
            'word_count' => str_word_count(strip_tags($data['content'])),
            'status' => 'draft',
        ]);

        $story->increment('episodes_count');

        return redirect()->route('studio.stories.episodes.index', $story->id)->with('status', 'Episode saved as a draft.');
    }

    public function edit(Request $request, int $storyId, int $episodeId)
    {
        $story = $this->ownedStoryOrFail($request, $storyId);
        $episode = $story->episodes()->findOrFail($episodeId);

        return view('studio.episodes.edit', compact('story', 'episode'));
    }

    public function update(Request $request, int $storyId, int $episodeId)
    {
        $story = $this->ownedStoryOrFail($request, $storyId);
        $episode = $story->episodes()->findOrFail($episodeId);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $episode->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'raw_content' => $data['content'],
            'raw_content_updated_at' => now(),
            'styled_at' => now(),
            'word_count' => str_word_count(strip_tags($data['content'])),
        ]);

        ReaderEpisodeController::forgetPreviewCache($story->slug, $episode->episode_number);

        return redirect()->route('studio.stories.episodes.edit', [$story->id, $episode->id])->with('status', 'Episode updated.');
    }

    public function publish(Request $request, int $storyId, int $episodeId)
    {
        $story = $this->ownedStoryOrFail($request, $storyId);
        $episode = $story->episodes()->findOrFail($episodeId);

        $episode->update(['status' => 'published', 'published_at' => $episode->published_at ?? now()]);
        ReaderEpisodeController::forgetPreviewCache($story->slug, $episode->episode_number);

        return back()->with('status', 'Episode published.');
    }

    public function destroy(Request $request, int $storyId, int $episodeId)
    {
        $story = $this->ownedStoryOrFail($request, $storyId);
        $episode = $story->episodes()->findOrFail($episodeId);
        $episode->delete();
        $story->decrement('episodes_count');

        ReaderEpisodeController::forgetPreviewCache($story->slug, $episode->episode_number);

        return redirect()->route('studio.stories.episodes.index', $story->id)->with('status', 'Episode deleted.');
    }

    private function ownedStoryOrFail(Request $request, int $storyId): Story
    {
        $user = $request->user();
        $story = Story::findOrFail($storyId);

        abort_unless(
            $user->role === 'admin' || $user->penNames()->where('id', $story->pen_name_id)->exists(),
            403,
            'You do not own this story.'
        );

        return $story;
    }
}

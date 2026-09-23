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

        // `content` starts out equal to the author's raw text (so it's
        // readable immediately) and gets replaced by the styled version once
        // StyleEpisodes picks it up, within 15 minutes - see that command and
        // EpisodeStylingAgent. `raw_content` is the source of truth CraftProfessor
        // is always sent (see CraftProfessorExportController) and is never
        // touched by the styling agent.
        $episode = $story->episodes()->create([
            'title' => $data['title'],
            'content' => $data['content'],
            'raw_content' => $data['content'],
            'raw_content_updated_at' => now(),
            'styled_at' => null,
            'styling_attempts' => 0,
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
            // Two independent fields now, not one - see EpisodeStylingAgent/
            // StyleEpisodes for why both exist at all. Sent separately
            // because they mean two different author actions: editing
            // raw_content is "I changed what the story says" (queues a
            // re-style, since the styled version is now stale); editing
            // content on its own is "I'm manually touching up the styling"
            // (e.g. fixing a spot the AI got wrong) and must NOT go back
            // through raw_content or the styling queue.
            'raw_content' => ['sometimes', 'string'],
            'content' => ['sometimes', 'string'],
        ]);

        $updates = collect($data)->only('title')->all();

        if (isset($data['raw_content'])) {
            $updates['raw_content'] = $data['raw_content'];
            $updates['raw_content_updated_at'] = now();
            $updates['word_count'] = str_word_count(strip_tags($data['raw_content']));

            // Only mirror + queue AI styling if this request ISN'T also
            // manually supplying a styled version below - if it is, that
            // manual version should win outright, not get queued for the AI
            // to immediately overwrite again.
            if (! isset($data['content'])) {
                $updates['content'] = $data['raw_content'];
                $updates['styled_at'] = null;
                $updates['styling_attempts'] = 0;
            }
        }

        if (isset($data['content'])) {
            // A manual styling save: written straight to `content`, marked
            // done (styled_at = now()) so StyleEpisodes leaves it alone -
            // raw_content is untouched here on purpose.
            $updates['content'] = $data['content'];
            $updates['styled_at'] = now();
            $updates['styling_attempts'] = 0;
        }

        $episode->update($updates);

        \App\Http\Controllers\Api\EpisodeController::forgetPreviewCache($story->slug, $episode->episode_number);

        return $this->ok($episode);
    }

    public function publish(Request $request, int $storyId, int $episodeId)
    {
        $story = $this->ownedStoryOrFail($request, $storyId);
        $episode = $story->episodes()->findOrFail($episodeId);

        $episode->update(['status' => 'published', 'published_at' => $episode->published_at ?? now()]);

        \App\Http\Controllers\Api\EpisodeController::forgetPreviewCache($story->slug, $episode->episode_number);
        // Publishing changes the episode-number list every guest-preview
        // cache entry embeds - see forgetGuestPreviewCaches' docblock.
        \App\Http\Controllers\Api\EpisodeController::forgetGuestPreviewCaches($story->slug);

        return $this->ok($episode);
    }

    public function destroy(Request $request, int $storyId, int $episodeId)
    {
        $story = $this->ownedStoryOrFail($request, $storyId);
        $episode = $story->episodes()->findOrFail($episodeId);
        $episode->delete();
        $story->decrement('episodes_count');

        \App\Http\Controllers\Api\EpisodeController::forgetPreviewCache($story->slug, $episode->episode_number);
        \App\Http\Controllers\Api\EpisodeController::forgetGuestPreviewCaches($story->slug);

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

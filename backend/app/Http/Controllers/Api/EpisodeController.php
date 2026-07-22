<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\StoryAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EpisodeController extends Controller
{
    public function __construct(private StoryAccessService $access) {}

    public function show(Request $request, string $slug, int $episodeNumber)
    {
        $story = Story::where('slug', $slug)->where('status', 'published')->firstOrFail();
        $episode = $story->publishedEpisodes()->where('episode_number', $episodeNumber)->firstOrFail();

        $user = $this->currentUser($request);

        if (! $this->access->canAccessEpisode($story, $episode, $user)) {
            return $this->error(
                $this->access->lockReason($story, $episode, $user),
                'This episode is locked.',
                403
            );
        }

        if ($user) {
            \App\Models\UserActivityEvent::create([
                'user_id' => $user->id,
                'event_type' => 'episode_opened',
                'metadata' => ['episode_id' => $episode->id, 'story_id' => $story->id],
                'created_at' => now(),
            ]);
            \App\Events\UserActivityLogged::dispatch($user->id, 'episode_opened', ['episode_id' => $episode->id, 'story_id' => $story->id]);
        }

        $progress = $user
            ? $episode->readingProgress()->where('user_id', $user->id)->value('progress_percent')
            : null;

        return $this->ok([
            'id' => $episode->id,
            'title' => $episode->title,
            'episode_number' => $episode->episode_number,
            'content' => $episode->content,
            'word_count' => $episode->word_count,
            'progress_percent' => $progress,
            'story' => ['slug' => $story->slug, 'title' => $story->title],
        ]);
    }
}

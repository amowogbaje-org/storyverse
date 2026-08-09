<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PageView;
use App\Models\Story;
use App\Services\AnalyticsQueryService;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsQueryService $analytics) {}

    /**
     * Public, fire-and-forget: the frontend calls this on route changes to track
     * "visits" per the project brief. Works for guests and signed-in readers alike.
     */
    public function trackPageview(Request $request)
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:512']]);

        PageView::create([
            'user_id' => $this->currentUser($request)?->id,
            'session_hash' => $this->sessionHash($request),
            'path' => $data['path'],
            'created_at' => now(),
        ]);

        return response()->json([], 204);
    }

    public function overview()
    {
        return $this->ok($this->analytics->overview());
    }

    public function timeseries(Request $request)
    {
        $data = $request->validate([
            'metric' => ['required', 'string'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);

        return $this->ok([
            'metric' => $data['metric'],
            'series' => $this->analytics->timeseries($data['metric'], $data['days'] ?? 30),
        ]);
    }

    public function topStories(Request $request)
    {
        $data = $request->validate(['days' => ['sometimes', 'integer', 'min:1', 'max:365']]);

        return $this->ok($this->analytics->topStories($data['days'] ?? 30));
    }

    public function funnel()
    {
        return $this->ok($this->analytics->funnel());
    }

    public function events(Request $request)
    {
        $data = $request->validate(['days' => ['sometimes', 'integer', 'min:1', 'max:365']]);

        return $this->ok($this->analytics->eventBreakdown($data['days'] ?? 30));
    }

    /** Weekly signup-cohort retention curve, platform-wide. */
    public function retention(Request $request)
    {
        $data = $request->validate([
            'cohort_weeks' => ['sometimes', 'integer', 'min:1', 'max:26'],
            'track_weeks' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ]);

        return $this->ok($this->analytics->retentionCohorts(
            $data['cohort_weeks'] ?? 8,
            $data['track_weeks'] ?? 5,
        ));
    }

    /** DAU/MAU stickiness ratio, trailing 30 days. */
    public function stickiness()
    {
        return $this->ok($this->analytics->stickiness());
    }

    /**
     * Per-episode reader drop-off for one story. Open to the story's own
     * author (via their pen names) as well as platform admins - unlike the
     * rest of this controller, this route isn't behind the admin-only
     * middleware group, so ownership is checked here.
     */
    public function episodeDropoff(Request $request, string $slug)
    {
        $user = $this->requireUser($request);
        $story = Story::where('slug', $slug)->firstOrFail();

        $owns = $user->role === 'admin'
            || $user->penNames()->pluck('id')->contains($story->pen_name_id);

        if (! $owns) {
            abort(403, "You don't have access to this story's analytics.");
        }

        return $this->ok([
            'story' => ['slug' => $story->slug, 'title' => $story->title],
            'episodes' => $this->analytics->episodeDropoff($story->id),
        ]);
    }
}

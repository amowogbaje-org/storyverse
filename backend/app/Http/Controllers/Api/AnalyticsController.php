<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PageView;
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
}

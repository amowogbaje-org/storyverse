<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\ReadingProgress;
use App\Models\Story;
use App\Services\PlatformMetricsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private PlatformMetricsService $metrics) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $storyIds = Story::whereIn('pen_name_id', $user->penNames()->pluck('id'))->pluck('id');

        return view('studio.dashboard', [
            'platform' => $user->role === 'admin' ? $this->metrics->status() : null,
            'storiesCount' => $storyIds->count(),
            'publishedStoriesCount' => Story::whereIn('id', $storyIds)->where('status', 'published')->count(),
            'totals' => [
                'views' => (int) Story::whereIn('id', $storyIds)->sum('views_count'),
                'likes' => (int) Story::whereIn('id', $storyIds)->sum('likes_count'),
                'bookmarks' => (int) Story::whereIn('id', $storyIds)->sum('bookmarks_count'),
                'reads' => ReadingProgress::whereIn('story_id', $storyIds)->count(),
                'completed_reads' => ReadingProgress::whereIn('story_id', $storyIds)->whereNotNull('completed_at')->count(),
            ],
            'stories' => Story::whereIn('id', $storyIds)
                ->withCount('episodes')
                ->orderByDesc('views_count')
                ->get(),
        ]);
    }
}

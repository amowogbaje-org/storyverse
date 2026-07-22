<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReadingProgress;
use App\Models\Story;
use App\Services\AnalyticsQueryService;
use Illuminate\Http\Request;

/**
 * "For admins on the panel ... they should also be able to see their stats to
 * know the progress they are making." Authors get their own story stats;
 * platform admins get the same platform-wide view as AnalyticsController.
 */
class DashboardController extends Controller
{
    public function __construct(private AnalyticsQueryService $analytics) {}

    public function mine(Request $request)
    {
        $user = $this->requireUser($request);

        if ($user->role === 'admin') {
            return $this->ok($this->analytics->overview());
        }

        $storyIds = Story::whereIn('pen_name_id', $user->penNames()->pluck('id'))->pluck('id');

        return $this->ok([
            'stories_count' => $storyIds->count(),
            'published_stories_count' => Story::whereIn('id', $storyIds)->where('status', 'published')->count(),
            'totals' => [
                'views' => (int) Story::whereIn('id', $storyIds)->sum('views_count'),
                'likes' => (int) Story::whereIn('id', $storyIds)->sum('likes_count'),
                'bookmarks' => (int) Story::whereIn('id', $storyIds)->sum('bookmarks_count'),
                'comments' => (int) Story::whereIn('id', $storyIds)->sum('comments_count'),
                'reads' => ReadingProgress::whereIn('story_id', $storyIds)->count(),
                'completed_reads' => ReadingProgress::whereIn('story_id', $storyIds)->whereNotNull('completed_at')->count(),
            ],
            'stories' => Story::whereIn('id', $storyIds)
                ->withCount('episodes')
                ->orderByDesc('views_count')
                ->get(['id', 'title', 'slug', 'status', 'views_count', 'likes_count', 'bookmarks_count', 'comments_count']),
        ]);
    }
}

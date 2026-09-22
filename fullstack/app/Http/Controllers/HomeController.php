<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Support\HomeCache;
use App\Support\StoryCardPresenter;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $newReleases = HomeCache::remember(HomeCache::NEW_RELEASES_KEY, fn () => Story::where('status', 'published')
            ->orderByDesc('published_at')
            ->with(['penName', 'categories'])
            ->limit(10)
            ->get());

        $popular = HomeCache::remember(HomeCache::POPULAR_KEY, fn () => Story::where('status', 'published')
            ->orderByDesc('views_count')
            ->with(['penName', 'categories'])
            ->limit(10)
            ->get());

        $ids = $newReleases->pluck('id')->merge($popular->pluck('id'))->unique();
        $progress = StoryCardPresenter::progressMap($user, $ids);
        $liked = StoryCardPresenter::likedMap($user, $ids);
        $bookmarked = StoryCardPresenter::bookmarkedMap($user, $ids);

        return view('home', compact('newReleases', 'popular', 'progress', 'liked', 'bookmarked'));
    }
}

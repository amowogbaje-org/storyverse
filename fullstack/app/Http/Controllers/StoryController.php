<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Genre;
use App\Models\ReadingProgress;
use App\Models\Story;
use App\Models\StoryView;
use App\Services\StoryAccessService;
use App\Support\StoryCardPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StoryController extends Controller
{
    public function __construct(private StoryAccessService $access) {}

    public function index(Request $request)
    {
        $query = Story::query()->where('status', 'published')->with(['penName', 'categories', 'genres']);

        if ($category = $request->query('category')) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $category));
        }
        if ($genre = $request->query('genre')) {
            $query->whereHas('genres', fn ($q) => $q->where('slug', $genre));
        }
        if ($q = $request->query('q')) {
            $query->where('title', 'like', "%{$q}%");
        }

        match ($request->query('sort', 'new')) {
            'popular' => $query->orderByDesc('views_count'),
            default => $query->orderByDesc('published_at'),
        };

        $stories = $query->paginate(24)->withQueryString();

        $user = $request->user();
        $ids = collect($stories->items())->pluck('id');
        $progress = StoryCardPresenter::progressMap($user, $ids);
        $liked = StoryCardPresenter::likedMap($user, $ids);
        $bookmarked = StoryCardPresenter::bookmarkedMap($user, $ids);

        $categories = Cache::remember('browse:categories', now()->addHour(), fn () => Category::orderBy('name')->get());
        $genres = Cache::remember('browse:genres', now()->addHour(), fn () => Genre::orderBy('name')->get());

        return view('stories.index', compact('stories', 'progress', 'liked', 'bookmarked', 'categories', 'genres'));
    }

    public function show(Request $request, string $slug)
    {
        $story = Story::where('slug', $slug)->where('status', 'published')
            ->with(['penName', 'categories', 'genres', 'publishedEpisodes', 'prices'])
            ->firstOrFail();

        $user = $request->user();

        if ($this->shouldCountView($request, $story, $user)) {
            StoryView::create([
                'user_id' => $user?->id,
                'story_id' => $story->id,
                'session_hash' => $this->sessionHash($request),
                'viewed_at' => now(),
            ]);
            $story->increment('views_count');
        }

        $limit = $this->access->accessibleEpisodeLimit($story, $user);

        $episodeProgress = $user
            ? ReadingProgress::where('user_id', $user->id)->where('story_id', $story->id)->pluck('progress_percent', 'episode_id')
            : collect();

        $episodes = $story->publishedEpisodes->map(function ($episode) use ($story, $user, $limit, $episodeProgress) {
            return [
                'model' => $episode,
                'locked' => $limit !== null && $episode->episode_number > $limit,
                'lock_reason' => $this->access->lockReasonForLimit($limit, $episode, $user),
                'progress' => $episodeProgress[$episode->id] ?? null,
            ];
        });

        $isLiked = $user ? $story->likes()->where('user_id', $user->id)->exists() : false;
        $isBookmarked = $user ? $story->bookmarks()->where('user_id', $user->id)->exists() : false;

        return view('stories.show', compact('story', 'episodes', 'isLiked', 'isBookmarked'));
    }

    /** Same one-view-per-visitor-per-hour dedup as before, now keyed off a real session id instead of an X-Session-Id header. */
    private function shouldCountView(Request $request, Story $story, $user): bool
    {
        $visitor = $user ? "user:{$user->id}" : 'guest:'.$this->sessionHash($request);
        $key = "story-view-seen:{$story->id}:{$visitor}";

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, true, now()->addHour());

        return true;
    }
}

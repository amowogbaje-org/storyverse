<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PenName;
use Illuminate\Http\Request;

class PenNameController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $penName = PenName::where('slug', $slug)->firstOrFail();
        $user = $this->currentUser($request);

        $paginator = $penName->stories()
            ->where('status', 'published')
            ->with(['penName', 'categories'])
            ->orderByDesc('published_at')
            ->cursorPaginate(20);

        $ids = collect($paginator->items())->pluck('id');
        $progress = \App\Support\StoryCardPresenter::progressMap($user, $ids);
        $liked = \App\Support\StoryCardPresenter::likedMap($user, $ids);
        $bookmarked = \App\Support\StoryCardPresenter::bookmarkedMap($user, $ids);

        return $this->ok([
            'display_name' => $penName->display_name,
            'slug' => $penName->slug,
            'bio' => $penName->bio,
            'avatar_url' => $penName->avatar_url,
            'stories_count' => $penName->stories()->where('status', 'published')->count(),
            // First page of stories embedded directly - the author page
            // always needs both together, so this replaces what used to be
            // a second /authors/{slug}/stories call. That endpoint (below)
            // is kept as-is for anything that needs to paginate past page 1.
            'stories' => [
                'data' => array_map(
                    fn ($story) => \App\Support\StoryCardPresenter::card($story, $user, $progress, $liked, $bookmarked),
                    $paginator->items()
                ),
                'meta' => [
                    'cursor' => $paginator->nextCursor()?->encode(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ],
        ]);
    }

    public function stories(Request $request, string $slug)
    {
        $penName = PenName::where('slug', $slug)->firstOrFail();
        $user = $this->currentUser($request);

        $paginator = $penName->stories()
            ->where('status', 'published')
            ->with(['penName', 'categories'])
            ->orderByDesc('published_at')
            ->cursorPaginate(20);

        $ids = collect($paginator->items())->pluck('id');
        $progress = \App\Support\StoryCardPresenter::progressMap($user, $ids);
        $liked = \App\Support\StoryCardPresenter::likedMap($user, $ids);
        $bookmarked = \App\Support\StoryCardPresenter::bookmarkedMap($user, $ids);

        return $this->paginated($paginator, fn ($story) => \App\Support\StoryCardPresenter::card($story, $user, $progress, $liked, $bookmarked));
    }
}

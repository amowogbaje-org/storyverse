<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PenName;
use Illuminate\Http\Request;

class PenNameController extends Controller
{
    public function show(string $slug)
    {
        $penName = PenName::where('slug', $slug)->firstOrFail();

        return $this->ok([
            'display_name' => $penName->display_name,
            'slug' => $penName->slug,
            'bio' => $penName->bio,
            'avatar_url' => $penName->avatar_url,
            'stories_count' => $penName->stories()->where('status', 'published')->count(),
        ]);
    }

    public function stories(Request $request, string $slug)
    {
        $penName = PenName::where('slug', $slug)->firstOrFail();
        $user = $this->currentUser($request);

        $paginator = $penName->stories()
            ->where('status', 'published')
            ->with(['penName', 'category'])
            ->orderByDesc('published_at')
            ->cursorPaginate(20);

        $progress = \App\Support\StoryCardPresenter::progressMap($user, collect($paginator->items())->pluck('id'));

        return $this->paginated($paginator, fn ($story) => \App\Support\StoryCardPresenter::card($story, $user, $progress));
    }
}

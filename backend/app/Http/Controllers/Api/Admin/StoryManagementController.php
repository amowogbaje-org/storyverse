<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PenName;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoryManagementController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->requireUser($request);

        $query = Story::with(['penName', 'category', 'genres'])->withCount('episodes');

        if ($user->role !== 'admin') {
            $query->whereIn('pen_name_id', $user->penNames()->pluck('id'));
        }

        $paginator = $query->orderByDesc('updated_at')->cursorPaginate(20);

        return $this->paginated($paginator);
    }

    public function show(Request $request, int $id)
    {
        $story = $this->ownedStoryOrFail($request, $id, ['penName', 'category', 'genres', 'episodes']);

        return $this->ok($story);
    }

    public function store(Request $request)
    {
        $user = $this->requireUser($request);

        $data = $request->validate([
            'pen_name_id' => ['required', 'exists:pen_names,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'genre_ids' => ['sometimes', 'array'],
            'genre_ids.*' => ['exists:genres,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'cover_image_url' => ['required', 'string', 'max:2048'],
            'access_type' => ['required', 'in:free,premium'],
        ]);

        $this->assertOwnsPenName($user, (int) $data['pen_name_id']);

        $story = Story::create([
            ...collect($data)->except('genre_ids')->all(),
            'slug' => $this->uniqueSlug($data['title']),
            'status' => 'draft',
        ]);

        if (! empty($data['genre_ids'])) {
            $story->genres()->sync($data['genre_ids']);
        }

        return $this->ok($story->load(['penName', 'category', 'genres']), 201);
    }

    public function update(Request $request, int $id)
    {
        $story = $this->ownedStoryOrFail($request, $id);

        $data = $request->validate([
            'category_id' => ['sometimes', 'exists:categories,id'],
            'genre_ids' => ['sometimes', 'array'],
            'genre_ids.*' => ['exists:genres,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'cover_image_url' => ['sometimes', 'string', 'max:2048'],
            'access_type' => ['sometimes', 'in:free,premium'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);

        $story->update(collect($data)->except('genre_ids')->all());

        if (array_key_exists('genre_ids', $data)) {
            $story->genres()->sync($data['genre_ids']);
        }

        \App\Support\HomeCache::forgetHomepage();

        return $this->ok($story->fresh(['penName', 'category', 'genres']));
    }

    public function publish(Request $request, int $id)
    {
        $story = $this->ownedStoryOrFail($request, $id, ['publishedEpisodes']);

        if ($story->publishedEpisodes->isEmpty()) {
            return $this->error('no_published_episodes', 'Publish at least one episode before publishing the story.', 422);
        }

        $story->update(['status' => 'published', 'published_at' => $story->published_at ?? now()]);

        \App\Support\HomeCache::forgetHomepage();

        return $this->ok($story);
    }

    public function unpublish(Request $request, int $id)
    {
        $story = $this->ownedStoryOrFail($request, $id);
        $story->update(['status' => 'draft']);

        \App\Support\HomeCache::forgetHomepage();

        return $this->ok($story);
    }

    public function destroy(Request $request, int $id)
    {
        $story = $this->ownedStoryOrFail($request, $id);
        $story->delete();

        \App\Support\HomeCache::forgetHomepage();

        return $this->ok(['deleted' => true]);
    }

    private function ownedStoryOrFail(Request $request, int $id, array $with = []): Story
    {
        $user = $this->requireUser($request);
        $story = Story::with($with)->findOrFail($id);

        if ($user->role !== 'admin' && ! $user->penNames()->where('id', $story->pen_name_id)->exists()) {
            abort(response()->json(['error' => ['code' => 'forbidden', 'message' => 'You do not own this story.']], 403));
        }

        return $story;
    }

    private function assertOwnsPenName($user, int $penNameId): void
    {
        if ($user->role === 'admin') {
            return;
        }

        if (! $user->penNames()->where('id', $penNameId)->exists()) {
            abort(response()->json(['error' => ['code' => 'forbidden', 'message' => 'You do not own this pen name.']], 403));
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;

        while (Story::where('slug', $slug)->exists()) {
            $slug = "{$base}-".(++$i);
        }

        return $slug;
    }
}

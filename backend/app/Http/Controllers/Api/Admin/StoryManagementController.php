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

        $query = Story::with(['penName', 'categories', 'genres', 'prices'])->withCount('episodes');

        if ($user->role !== 'admin') {
            $query->whereIn('pen_name_id', $user->penNames()->pluck('id'));
        }

        $paginator = $query->orderByDesc('updated_at')->cursorPaginate(20);

        return $this->paginated($paginator);
    }

    public function show(Request $request, int $id)
    {
        $story = $this->ownedStoryOrFail($request, $id, ['penName', 'categories', 'genres', 'episodes', 'prices']);

        return $this->ok($story);
    }

    public function store(Request $request)
    {
        $user = $this->requireUser($request);

        $data = $request->validate([
            'pen_name_id' => ['required', 'exists:pen_names,id'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['exists:categories,id'],
            'genre_ids' => ['sometimes', 'array'],
            'genre_ids.*' => ['exists:genres,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'cover_image_url' => ['required', 'string', 'max:2048'],
            'cover_image_thumb_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'access_type' => ['required', 'in:free,premium'],
            ...$this->pricesValidationRules(),
        ]);

        $this->assertOwnsPenName($user, (int) $data['pen_name_id']);

        $story = Story::create([
            ...collect($data)->except(['genre_ids', 'category_ids', 'prices'])->all(),
            'slug' => $this->uniqueSlug($data['title']),
            'status' => 'draft',
        ]);

        $story->categories()->sync($data['category_ids']);

        if (! empty($data['genre_ids'])) {
            $story->genres()->sync($data['genre_ids']);
        }

        if (array_key_exists('prices', $data)) {
            $this->syncPrices($story, $data['prices'] ?? []);
        }

        return $this->ok($story->load(['penName', 'categories', 'genres', 'prices']), 201);
    }

    public function update(Request $request, int $id)
    {
        $story = $this->ownedStoryOrFail($request, $id);

        $data = $request->validate([
            'category_ids' => ['sometimes', 'array', 'min:1'],
            'category_ids.*' => ['exists:categories,id'],
            'genre_ids' => ['sometimes', 'array'],
            'genre_ids.*' => ['exists:genres,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'cover_image_url' => ['sometimes', 'string', 'max:2048'],
            // 'sometimes' (not 'required') so a request that doesn't touch
            // the cover at all (e.g. just editing the title) doesn't need to
            // resend it - but the frontend always sends this alongside
            // cover_image_url whenever the cover itself changes (explicitly
            // null for the "paste a URL" path, which has no generated
            // thumbnail), so a stale thumb from a previous image is never
            // left paired with a new full image. See AdminStoryEditorPage's
            // saveDetails().
            'cover_image_thumb_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'access_type' => ['sometimes', 'in:free,premium'],
            'is_completed' => ['sometimes', 'boolean'],
            ...$this->pricesValidationRules(),
        ]);

        $story->update(collect($data)->except(['genre_ids', 'category_ids', 'prices'])->all());

        if (array_key_exists('category_ids', $data)) {
            $story->categories()->sync($data['category_ids']);
        }

        if (array_key_exists('genre_ids', $data)) {
            $story->genres()->sync($data['genre_ids']);
        }

        if (array_key_exists('prices', $data)) {
            $this->syncPrices($story, $data['prices'] ?? []);
        }

        \App\Support\HomeCache::forgetHomepage();
        // title/description/cover_image_url/access_type are embedded in
        // EpisodeController's cached guest-preview payload too (see its
        // loadEpisodePayload) - same staleness concern as
        // EpisodeManagementController::publish/destroy.
        \App\Http\Controllers\Api\EpisodeController::forgetGuestPreviewCaches($story->slug);

        return $this->ok($story->fresh(['penName', 'categories', 'genres', 'prices']));
    }

    public function publish(Request $request, int $id)
    {
        $story = $this->ownedStoryOrFail($request, $id, ['publishedEpisodes']);
        $minEpisodes = (int) config('access.min_episodes_to_publish');

        if ($story->publishedEpisodes->count() < $minEpisodes) {
            return $this->error(
                'insufficient_episodes',
                "Publish at least {$minEpisodes} episodes before publishing the story (currently {$story->publishedEpisodes->count()}). This gives readers enough of the story to really get hooked before they hit anything asking for payment.",
                422
            );
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

    /**
     * One price per currency an author wants to sell a story in - e.g.
     * [{"currency":"USD","amount":9.99},{"currency":"NGN","amount":4500}].
     * Any currency not in config/currencies.php is rejected here rather than
     * silently accepted, since that config file is also what the frontend's
     * price form and StoryPurchaseController's checkout both read from - a
     * currency this validation let through but nothing else recognized would
     * be a silently broken price no reader could actually pay.
     */
    private function pricesValidationRules(): array
    {
        return [
            'prices' => ['sometimes', 'array'],
            'prices.*.currency' => ['required', 'string', 'in:'.implode(',', array_keys(config('currencies')))],
            'prices.*.amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
        ];
    }

    /** Replaces a story's full set of prices with exactly the currencies given - removing a currency from the list un-prices it. */
    private function syncPrices(Story $story, array $prices): void
    {
        $keep = collect($prices)->pluck('currency');

        $story->prices()->whereNotIn('currency', $keep)->delete();

        foreach ($prices as $price) {
            $story->prices()->updateOrCreate(
                ['currency' => $price['currency']],
                ['amount' => $price['amount']]
            );
        }
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

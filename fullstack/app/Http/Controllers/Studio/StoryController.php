<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Genre;
use App\Models\Story;
use App\Support\HomeCache;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Story::with(['penName', 'categories'])->withCount('episodes');

        if ($user->role !== 'admin') {
            $query->whereIn('pen_name_id', $user->penNames()->pluck('id'));
        }

        $stories = $query->orderByDesc('updated_at')->paginate(20);

        return view('studio.stories.index', compact('stories'));
    }

    public function create(Request $request)
    {
        $user = $request->user();

        $penNames = $user->role === 'admin' ? \App\Models\PenName::all() : $user->penNames;
        abort_if($penNames->isEmpty(), 422, 'Create a pen name first.');

        return view('studio.stories.create', [
            'penNames' => $penNames,
            'categories' => Category::orderBy('name')->get(),
            'genres' => Genre::orderBy('name')->get(),
            'currencies' => config('currencies'),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->stripEmptyPriceRows($request);

        $data = $request->validate([
            'pen_name_id' => ['required', 'exists:pen_names,id'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['exists:categories,id'],
            'genre_ids' => ['sometimes', 'array'],
            'genre_ids.*' => ['exists:genres,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'cover_image_url' => ['required', 'string', 'max:2048'],
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

        return redirect()->route('studio.stories.edit', $story->id)->with('status', 'Story created as a draft. Add episodes, then publish when ready.');
    }

    public function edit(Request $request, int $id)
    {
        $story = $this->ownedStoryOrFail($request, $id, ['penName', 'categories', 'genres', 'episodes', 'prices']);
        $user = $request->user();

        return view('studio.stories.edit', [
            'story' => $story,
            'penNames' => $user->role === 'admin' ? \App\Models\PenName::all() : $user->penNames,
            'categories' => Category::orderBy('name')->get(),
            'genres' => Genre::orderBy('name')->get(),
            'currencies' => config('currencies'),
            'minEpisodesToPublish' => (int) config('access.min_episodes_to_publish'),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $story = $this->ownedStoryOrFail($request, $id);
        $this->stripEmptyPriceRows($request);

        $data = $request->validate([
            'category_ids' => ['sometimes', 'array', 'min:1'],
            'category_ids.*' => ['exists:categories,id'],
            'genre_ids' => ['sometimes', 'array'],
            'genre_ids.*' => ['exists:genres,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'cover_image_url' => ['sometimes', 'string', 'max:2048'],
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

        HomeCache::forgetHomepage();

        return redirect()->route('studio.stories.edit', $story->id)->with('status', 'Story updated.');
    }

    public function publish(Request $request, int $id)
    {
        $story = $this->ownedStoryOrFail($request, $id, ['publishedEpisodes']);
        $minEpisodes = (int) config('access.min_episodes_to_publish');

        if ($story->publishedEpisodes->count() < $minEpisodes) {
            return back()->withErrors(['publish' => "Publish at least {$minEpisodes} episodes before publishing the story (currently {$story->publishedEpisodes->count()})."]);
        }

        $story->update(['status' => 'published', 'published_at' => $story->published_at ?? now()]);
        HomeCache::forgetHomepage();

        return back()->with('status', 'Story published.');
    }

    public function unpublish(Request $request, int $id)
    {
        $story = $this->ownedStoryOrFail($request, $id);
        $story->update(['status' => 'draft']);
        HomeCache::forgetHomepage();

        return back()->with('status', 'Story unpublished.');
    }

    public function destroy(Request $request, int $id)
    {
        $story = $this->ownedStoryOrFail($request, $id);
        $story->delete();
        HomeCache::forgetHomepage();

        return redirect()->route('studio.stories.index')->with('status', 'Story deleted.');
    }

    /** The edit/create forms always render one trailing blank "add a currency" row - drop it before validation if the author left it empty. */
    private function stripEmptyPriceRows(Request $request): void
    {
        $prices = collect($request->input('prices', []))
            ->filter(fn ($row) => ! empty($row['currency']) && $row['amount'] !== null && $row['amount'] !== '')
            ->values()
            ->all();

        $request->merge(['prices' => $prices]);
    }

    private function pricesValidationRules(): array
    {
        return [
            'prices' => ['sometimes', 'array'],
            'prices.*.currency' => ['required', 'string', 'in:'.implode(',', array_keys(config('currencies')))],
            'prices.*.amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
        ];
    }

    private function syncPrices(Story $story, array $prices): void
    {
        $keep = collect($prices)->pluck('currency');
        $story->prices()->whereNotIn('currency', $keep)->delete();

        foreach ($prices as $price) {
            $story->prices()->updateOrCreate(['currency' => $price['currency']], ['amount' => $price['amount']]);
        }
    }

    private function ownedStoryOrFail(Request $request, int $id, array $with = []): Story
    {
        $user = $request->user();
        $story = Story::with($with)->findOrFail($id);

        abort_unless(
            $user->role === 'admin' || $user->penNames()->where('id', $story->pen_name_id)->exists(),
            403,
            'You do not own this story.'
        );

        return $story;
    }

    private function assertOwnsPenName($user, int $penNameId): void
    {
        if ($user->role === 'admin') {
            return;
        }

        abort_unless($user->penNames()->where('id', $penNameId)->exists(), 403, 'You do not own this pen name.');
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

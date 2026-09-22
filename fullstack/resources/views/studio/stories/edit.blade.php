@extends('layouts.app')

@section('title', 'Edit '.$story->title.' - Studio')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="font-serif text-2xl font-semibold">{{ $story->title }}</h1>
        <div class="flex gap-2">
            <a href="{{ route('studio.stories.episodes.index', $story->id) }}" class="rounded-full border border-stone-300 px-4 py-1.5 text-sm">
                Episodes ({{ $story->episodes->count() }})
            </a>
            @if($story->status === 'published')
                <form method="POST" action="{{ route('studio.stories.unpublish', $story->id) }}">
                    @csrf
                    <button type="submit" class="rounded-full border border-stone-300 px-4 py-1.5 text-sm">Unpublish</button>
                </form>
            @else
                <form method="POST" action="{{ route('studio.stories.publish', $story->id) }}">
                    @csrf
                    <button type="submit" class="rounded-full bg-emerald-600 px-4 py-1.5 text-sm text-white">Publish</button>
                </form>
            @endif
        </div>
    </div>

    @if($story->status !== 'published' && $story->episodes->where('status', 'published')->count() < $minEpisodesToPublish)
        <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-700">
            Publish at least {{ $minEpisodesToPublish }} episodes before you can publish this story
            (currently {{ $story->episodes->where('status', 'published')->count() }}).
        </p>
    @endif

    <form method="POST" action="{{ route('studio.stories.update', $story->id) }}" class="max-w-xl space-y-4" x-data="{ accessType: '{{ $story->access_type }}' }">
        @csrf
        @method('PATCH')

        <div>
            <label class="mb-1 block text-sm font-medium">Title</label>
            <input type="text" name="title" value="{{ old('title', $story->title) }}" required class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Description</label>
            <textarea name="description" rows="5" required class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('description', $story->description) }}</textarea>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Cover image URL</label>
            <input type="text" name="cover_image_url" value="{{ old('cover_image_url', $story->cover_image_url) }}" required class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Categories</label>
            <select name="category_ids[]" multiple required class="h-28 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected($story->categories->contains('id', $category->id))>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Genres</label>
            <select name="genre_ids[]" multiple class="h-28 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                @foreach($genres as $genre)
                    <option value="{{ $genre->id }}" @selected($story->genres->contains('id', $genre->id))>{{ $genre->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Access</label>
            <select name="access_type" x-model="accessType" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                <option value="free">Free</option>
                <option value="premium">Premium (purchasable)</option>
            </select>
        </div>

        <div x-show="accessType === 'premium'" x-cloak>
            <label class="mb-1 block text-sm font-medium">Prices</label>
            @foreach($story->prices as $i => $price)
                <div class="mb-2 flex gap-2">
                    <select name="prices[{{ $i }}][currency]" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
                        @foreach(array_keys($currencies) as $code)
                            <option value="{{ $code }}" @selected($price->currency === $code)>{{ $code }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" min="0.01" name="prices[{{ $i }}][amount]" value="{{ $price->amount }}"
                           class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
            @endforeach
            @php $nextIndex = $story->prices->count(); @endphp
            <div class="flex gap-2">
                <select name="prices[{{ $nextIndex }}][currency]" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    <option value="">Add currency…</option>
                    @foreach(array_keys($currencies) as $code)
                        <option value="{{ $code }}">{{ $code }}</option>
                    @endforeach
                </select>
                <input type="number" step="0.01" min="0.01" name="prices[{{ $nextIndex }}][amount]" placeholder="Amount"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <p class="mt-1 text-xs text-stone-400">Leave the "Add currency" row blank to leave prices unchanged.</p>
        </div>

        <label class="flex items-center gap-1.5 text-sm text-stone-600">
            <input type="checkbox" name="is_completed" value="1" @checked($story->is_completed)> Story is complete
        </label>

        <button type="submit" class="rounded-full bg-stone-900 px-5 py-2 text-sm font-medium text-white">Save changes</button>
    </form>

    <form method="POST" action="{{ route('studio.stories.destroy', $story->id) }}" class="mt-8"
          onsubmit="return confirm('Delete this story and all its episodes? This cannot be undone.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="text-sm text-red-600 hover:underline">Delete story</button>
    </form>
@endsection

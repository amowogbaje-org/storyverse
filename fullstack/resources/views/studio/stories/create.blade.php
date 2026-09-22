@extends('layouts.app')

@section('title', 'New story - Studio')

@section('content')
    <h1 class="mb-6 font-serif text-2xl font-semibold">New story</h1>

    <form method="POST" action="{{ route('studio.stories.store') }}" class="max-w-xl space-y-4" x-data="{ accessType: 'free' }">
        @csrf

        <div>
            <label class="mb-1 block text-sm font-medium">Pen name</label>
            <select name="pen_name_id" required class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                @foreach($penNames as $penName)
                    <option value="{{ $penName->id }}">{{ $penName->display_name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Title</label>
            <input type="text" name="title" value="{{ old('title') }}" required class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Description</label>
            <textarea name="description" rows="5" required class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('description') }}</textarea>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Cover image URL</label>
            <input type="text" name="cover_image_url" value="{{ old('cover_image_url') }}" required class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-stone-400">Upload an image via the studio's image endpoint, or paste a hosted URL.</p>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Categories</label>
            <select name="category_ids[]" multiple required class="h-28 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Genres</label>
            <select name="genre_ids[]" multiple class="h-28 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                @foreach($genres as $genre)
                    <option value="{{ $genre->id }}">{{ $genre->name }}</option>
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
            <label class="mb-1 block text-sm font-medium">Price</label>
            <div class="flex gap-2">
                <select name="prices[0][currency]" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    @foreach(array_keys($currencies) as $code)
                        <option value="{{ $code }}">{{ $code }}</option>
                    @endforeach
                </select>
                <input type="number" step="0.01" min="0.01" name="prices[0][amount]" placeholder="Amount"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <p class="mt-1 text-xs text-stone-400">Add more currencies later from the edit page.</p>
        </div>

        <button type="submit" class="rounded-full bg-stone-900 px-5 py-2 text-sm font-medium text-white">Create story</button>
    </form>
@endsection

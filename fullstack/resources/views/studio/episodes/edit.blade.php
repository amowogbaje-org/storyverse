@extends('layouts.app')

@section('title', 'Edit episode - '.$story->title)

@section('content')
    <a href="{{ route('studio.stories.episodes.index', $story->id) }}" class="text-sm text-stone-500 hover:text-stone-900">&larr; Episodes</a>
    <h1 class="mb-6 mt-1 font-serif text-2xl font-semibold">Episode {{ $episode->episode_number }}</h1>

    <form method="POST" action="{{ route('studio.stories.episodes.update', [$story->id, $episode->id]) }}" class="max-w-2xl space-y-4">
        @csrf
        @method('PATCH')
        <div>
            <label class="mb-1 block text-sm font-medium">Title</label>
            <input type="text" name="title" value="{{ old('title', $episode->title) }}" required class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Content</label>
            <textarea name="content" rows="20" required
                      class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm leading-relaxed">{{ old('content', $episode->content) }}</textarea>
        </div>
        <button type="submit" class="rounded-full bg-stone-900 px-5 py-2 text-sm font-medium text-white">Save changes</button>
    </form>

    <form method="POST" action="{{ route('studio.stories.episodes.destroy', [$story->id, $episode->id]) }}" class="mt-6"
          onsubmit="return confirm('Delete this episode? This cannot be undone.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="text-sm text-red-600 hover:underline">Delete episode</button>
    </form>
@endsection

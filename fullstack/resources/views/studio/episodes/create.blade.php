@extends('layouts.app')

@section('title', 'New episode - '.$story->title)

@section('content')
    <a href="{{ route('studio.stories.episodes.index', $story->id) }}" class="text-sm text-stone-500 hover:text-stone-900">&larr; Episodes</a>
    <h1 class="mb-6 mt-1 font-serif text-2xl font-semibold">New episode</h1>

    <form method="POST" action="{{ route('studio.stories.episodes.store', $story->id) }}" class="max-w-2xl space-y-4">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium">Title</label>
            <input type="text" name="title" value="{{ old('title') }}" required class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Content</label>
            <textarea name="content" rows="20" required
                      class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm leading-relaxed">{{ old('content') }}</textarea>
            <p class="mt-1 text-xs text-stone-400">Plain text or simple HTML (e.g. &lt;p&gt; tags) - this renders as-is on the reader page.</p>
        </div>
        <button type="submit" class="rounded-full bg-stone-900 px-5 py-2 text-sm font-medium text-white">Save as draft</button>
    </form>
@endsection

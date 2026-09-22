@extends('layouts.app')

@section('title', 'Your stories - Studio')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="font-serif text-2xl font-semibold">Stories</h1>
        <a href="{{ route('studio.stories.create') }}" class="rounded-full bg-stone-900 px-4 py-1.5 text-sm text-white">New story</a>
    </div>

    <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
        @forelse($stories as $story)
            <li class="flex items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                    <a href="{{ route('studio.stories.edit', $story->id) }}" class="truncate text-sm font-medium hover:underline">{{ $story->title }}</a>
                    <p class="text-xs text-stone-400">
                        {{ $story->penName?->display_name }} &middot; {{ $story->episodes_count }} episodes &middot;
                        <span class="{{ $story->status === 'published' ? 'text-emerald-600' : 'text-stone-500' }}">{{ $story->status }}</span>
                    </p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <a href="{{ route('studio.stories.episodes.index', $story->id) }}" class="rounded-full border border-stone-300 px-3 py-1 text-xs">Episodes</a>
                    <a href="{{ route('studio.stories.edit', $story->id) }}" class="rounded-full border border-stone-300 px-3 py-1 text-xs">Edit</a>
                </div>
            </li>
        @empty
            <li class="px-4 py-8 text-center text-sm text-stone-500">No stories yet.</li>
        @endforelse
    </ul>

    <div class="mt-6">{{ $stories->links() }}</div>
@endsection

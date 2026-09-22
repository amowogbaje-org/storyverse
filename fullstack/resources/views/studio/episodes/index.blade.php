@extends('layouts.app')

@section('title', 'Episodes - '.$story->title)

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="{{ route('studio.stories.edit', $story->id) }}" class="text-sm text-stone-500 hover:text-stone-900">&larr; {{ $story->title }}</a>
            <h1 class="font-serif text-2xl font-semibold">Episodes</h1>
        </div>
        <a href="{{ route('studio.stories.episodes.create', $story->id) }}" class="rounded-full bg-stone-900 px-4 py-1.5 text-sm text-white">New episode</a>
    </div>

    <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
        @forelse($episodes as $episode)
            <li class="flex items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                    <a href="{{ route('studio.stories.episodes.edit', [$story->id, $episode->id]) }}" class="truncate text-sm font-medium hover:underline">
                        {{ $episode->episode_number }}. {{ $episode->title }}
                    </a>
                    <p class="text-xs text-stone-400">
                        {{ number_format($episode->word_count) }} words &middot;
                        <span class="{{ $episode->status === 'published' ? 'text-emerald-600' : 'text-stone-500' }}">{{ $episode->status }}</span>
                    </p>
                </div>
                <div class="flex shrink-0 gap-2">
                    @if($episode->status !== 'published')
                        <form method="POST" action="{{ route('studio.stories.episodes.publish', [$story->id, $episode->id]) }}">
                            @csrf
                            <button type="submit" class="rounded-full border border-stone-300 px-3 py-1 text-xs">Publish</button>
                        </form>
                    @endif
                    <a href="{{ route('studio.stories.episodes.edit', [$story->id, $episode->id]) }}" class="rounded-full border border-stone-300 px-3 py-1 text-xs">Edit</a>
                </div>
            </li>
        @empty
            <li class="px-4 py-8 text-center text-sm text-stone-500">No episodes yet.</li>
        @endforelse
    </ul>
@endsection

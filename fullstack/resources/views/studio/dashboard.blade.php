@extends('layouts.app')

@section('title', 'Studio - Storyverse')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="font-serif text-2xl font-semibold">Studio</h1>
        <div class="flex gap-2">
            <a href="{{ route('studio.pen-names.index') }}" class="rounded-full border border-stone-300 px-4 py-1.5 text-sm">Pen names</a>
            <a href="{{ route('studio.stories.create') }}" class="rounded-full bg-stone-900 px-4 py-1.5 text-sm text-white">New story</a>
        </div>
    </div>

    @if($platform)
        <div class="mb-6 rounded-xl border border-stone-200 bg-white p-4 text-sm">
            <p class="font-medium">Platform monetization: {{ $platform['monetization_enabled'] ? 'Enabled' : 'Not yet enabled' }}</p>
            <p class="mt-1 text-stone-500">
                {{ number_format($platform['reads']) }} / {{ number_format($platform['reads_threshold']) }} total reads,
                {{ number_format($platform['completed_reads']) }} / {{ number_format($platform['completed_reads_threshold']) }} completed reads.
            </p>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach([
            'Stories' => $storiesCount,
            'Published' => $publishedStoriesCount,
            'Views' => $totals['views'],
            'Likes' => $totals['likes'],
            'Completed reads' => $totals['completed_reads'],
        ] as $label => $value)
            <div class="rounded-xl border border-stone-200 bg-white p-3 text-center">
                <p class="text-xl font-semibold">{{ number_format($value) }}</p>
                <p class="text-xs text-stone-500">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    <h2 class="mb-3 font-serif text-lg font-semibold">Your stories</h2>
    <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
        @forelse($stories as $story)
            <li class="flex items-center justify-between px-4 py-3">
                <div>
                    <a href="{{ route('studio.stories.edit', $story->id) }}" class="text-sm font-medium hover:underline">{{ $story->title }}</a>
                    <p class="text-xs text-stone-400">{{ $story->episodes_count }} episodes &middot; {{ $story->status }}</p>
                </div>
                <span class="text-xs text-stone-500">{{ number_format($story->views_count) }} views</span>
            </li>
        @empty
            <li class="px-4 py-8 text-center text-sm text-stone-500">No stories yet. <a href="{{ route('studio.stories.create') }}" class="underline">Create your first one</a>.</li>
        @endforelse
    </ul>
@endsection

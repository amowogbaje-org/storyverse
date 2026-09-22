@extends('layouts.app')

@section('title', $episode->title.' - '.$story->title)

@section('content')
    <div x-data="episodeReader({
            story: '{{ $story->slug }}',
            episode: {{ $episode->episode_number }},
            csrf: document.querySelector('meta[name=csrf-token]').content,
            authed: {{ auth()->check() ? 'true' : 'false' }},
         })" x-init="init()" class="mx-auto max-w-2xl">

        <div class="mb-6 flex items-center justify-between text-sm">
            <a href="{{ route('stories.show', $story->slug) }}" class="text-stone-500 hover:text-stone-900">&larr; {{ $story->title }}</a>
            <span class="text-stone-400">Episode {{ $episode->episode_number }}</span>
        </div>

        <h1 class="mb-6 font-serif text-2xl font-semibold">{{ $episode->title }}</h1>

        <article class="prose prose-stone max-w-none text-[17px] leading-8">
            {!! $episode->content !!}
        </article>

        <div class="mt-10 flex items-center justify-between border-t border-stone-200 pt-6 text-sm">
            @if($prevNumber)
                <a href="{{ route('episodes.show', [$story->slug, $prevNumber]) }}" class="text-stone-600 hover:text-stone-900">&larr; Episode {{ $prevNumber }}</a>
            @else
                <span></span>
            @endif
            @if($nextNumber)
                <a href="{{ route('episodes.show', [$story->slug, $nextNumber]) }}" class="text-stone-600 hover:text-stone-900">Episode {{ $nextNumber }} &rarr;</a>
            @endif
        </div>
    </div>
@endsection

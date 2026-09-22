@extends('layouts.app')

@section('title', 'Storyverse - Read serialized fiction')

@section('content')
    <section class="mb-10 rounded-2xl bg-stone-900 px-6 py-10 text-center text-white">
        <h1 class="font-serif text-3xl font-semibold">Stories worth staying up for</h1>
        <p class="mx-auto mt-2 max-w-md text-sm text-stone-300">
            Serialized fiction from independent authors, free to start reading right now.
        </p>
        <a href="{{ route('stories.index') }}" class="mt-4 inline-block rounded-full bg-white px-5 py-2 text-sm font-medium text-stone-900">
            Browse all stories
        </a>
    </section>

    @if($newReleases->isNotEmpty())
        <section class="mb-10">
            <h2 class="mb-3 font-serif text-lg font-semibold">New releases</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-5">
                @foreach($newReleases as $story)
                    @include('partials.story-card', ['story' => $story])
                @endforeach
            </div>
        </section>
    @endif

    @if($popular->isNotEmpty())
        <section>
            <h2 class="mb-3 font-serif text-lg font-semibold">Popular right now</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-5">
                @foreach($popular as $story)
                    @include('partials.story-card', ['story' => $story])
                @endforeach
            </div>
        </section>
    @endif
@endsection

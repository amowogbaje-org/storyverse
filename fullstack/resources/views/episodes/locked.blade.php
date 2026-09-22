@extends('layouts.app')

@section('title', 'Episode locked - '.$story->title)

@section('content')
    <div class="mx-auto max-w-md rounded-2xl border border-stone-200 bg-white p-8 text-center">
        <h1 class="font-serif text-xl font-semibold">{{ $episode->title }} is locked</h1>

        @if($reason === 'guest_limit')
            <p class="mt-2 text-sm text-stone-600">
                You've reached the free preview limit as a guest. Create a free account to keep reading.
            </p>
            <a href="{{ route('auth.register.show') }}" class="mt-5 inline-block rounded-full bg-stone-900 px-5 py-2 text-sm text-white">
                Create a free account
            </a>
        @else
            <p class="mt-2 text-sm text-stone-600">
                This is a premium story. Buy it once to unlock every episode, or check back once you're subscribed.
            </p>
            <a href="{{ route('stories.show', $story->slug) }}" class="mt-5 inline-block rounded-full bg-amber-500 px-5 py-2 text-sm font-medium text-white">
                View purchase options
            </a>
        @endif
    </div>
@endsection

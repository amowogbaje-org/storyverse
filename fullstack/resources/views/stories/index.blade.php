@extends('layouts.app')

@section('title', 'Browse stories - Storyverse')

@section('content')
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search titles…"
               class="w-full max-w-xs rounded-full border border-stone-300 px-4 py-1.5 text-sm sm:w-auto">

        <select name="category" onchange="this.form.submit()" class="rounded-full border border-stone-300 px-3 py-1.5 text-sm">
            <option value="">All categories</option>
            @foreach($categories as $category)
                <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>
            @endforeach
        </select>

        <select name="genre" onchange="this.form.submit()" class="rounded-full border border-stone-300 px-3 py-1.5 text-sm">
            <option value="">All genres</option>
            @foreach($genres as $genre)
                <option value="{{ $genre->slug }}" @selected(request('genre') === $genre->slug)>{{ $genre->name }}</option>
            @endforeach
        </select>

        <select name="sort" onchange="this.form.submit()" class="rounded-full border border-stone-300 px-3 py-1.5 text-sm">
            <option value="new" @selected(request('sort', 'new') === 'new')>Newest</option>
            <option value="popular" @selected(request('sort') === 'popular')>Most popular</option>
        </select>

        <button type="submit" class="rounded-full bg-stone-900 px-4 py-1.5 text-sm text-white">Search</button>
    </form>

    @if($stories->isEmpty())
        <p class="py-12 text-center text-sm text-stone-500">No stories match these filters yet.</p>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-5">
            @foreach($stories as $story)
                @include('partials.story-card', ['story' => $story])
            @endforeach
        </div>

        <div class="mt-8">{{ $stories->links() }}</div>
    @endif
@endsection

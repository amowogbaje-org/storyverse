@extends('layouts.app')

@section('title', $story->title.' - Storyverse')
@section('description', Str::limit(strip_tags($story->description), 155))

@section('content')
    <div class="flex flex-col gap-6 sm:flex-row">
        <div class="w-40 shrink-0 overflow-hidden rounded-xl bg-stone-100 sm:w-56">
            @if($story->cover_image_url)
                <img src="{{ $story->cover_image_url }}" alt="{{ $story->title }}" class="aspect-[3/4] w-full object-cover">
            @else
                <div class="flex aspect-[3/4] items-center justify-center text-stone-300">
                    <span class="font-serif text-5xl">{{ Str::substr($story->title, 0, 1) }}</span>
                </div>
            @endif
        </div>

        <div class="flex-1">
            <h1 class="font-serif text-2xl font-semibold">{{ $story->title }}</h1>
            <p class="mt-1 text-sm text-stone-500">by {{ $story->penName?->display_name ?? 'Unknown author' }}</p>

            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                @foreach($story->categories as $category)
                    <span class="rounded-full bg-stone-100 px-2 py-1 text-stone-600">{{ $category->name }}</span>
                @endforeach
                @foreach($story->genres as $genre)
                    <span class="rounded-full bg-stone-100 px-2 py-1 text-stone-600">{{ $genre->name }}</span>
                @endforeach
                @if($story->access_type === 'premium')
                    <span class="rounded-full bg-amber-100 px-2 py-1 text-amber-700">Premium</span>
                @endif
                @if($story->is_completed)
                    <span class="rounded-full bg-emerald-100 px-2 py-1 text-emerald-700">Completed</span>
                @endif
            </div>

            <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-stone-700">{{ $story->description }}</p>

            <div class="mt-4 flex items-center gap-3 text-sm text-stone-500">
                <span>{{ number_format($story->views_count) }} reads</span>
                <span>{{ number_format($story->likes_count) }} likes</span>
                <span>{{ number_format($story->comments_count) }} comments</span>
            </div>

            @auth
                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="{{ $isLiked ? route('stories.unlike', $story->slug) : route('stories.like', $story->slug) }}">
                        @csrf
                        @if($isLiked) @method('DELETE') @endif
                        <button type="submit" class="rounded-full border border-stone-300 px-4 py-1.5 text-sm {{ $isLiked ? 'bg-stone-900 text-white' : '' }}">
                            {{ $isLiked ? 'Liked' : 'Like' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ $isBookmarked ? route('stories.unbookmark', $story->slug) : route('stories.bookmark', $story->slug) }}">
                        @csrf
                        @if($isBookmarked) @method('DELETE') @endif
                        <button type="submit" class="rounded-full border border-stone-300 px-4 py-1.5 text-sm {{ $isBookmarked ? 'bg-stone-900 text-white' : '' }}">
                            {{ $isBookmarked ? 'Bookmarked' : 'Bookmark' }}
                        </button>
                    </form>

                    @if($story->isPurchasable())
                        @php $price = $story->priceFor(auth()->user()->currency); @endphp
                        <form method="POST" action="{{ route('stories.purchase', $story->slug) }}">
                            @csrf
                            <input type="hidden" name="gateway" value="paystack">
                            <button type="submit" class="rounded-full bg-amber-500 px-4 py-1.5 text-sm font-medium text-white">
                                Buy for {{ $price?->currency }} {{ number_format((float) $price?->amount, 2) }}
                            </button>
                        </form>
                    @endif
                </div>
            @else
                <a href="{{ route('auth.login.show') }}" class="mt-4 inline-block rounded-full bg-stone-900 px-4 py-1.5 text-sm text-white">
                    Sign in to like, bookmark, or buy
                </a>
            @endauth
        </div>
    </div>

    <section class="mt-10">
        <h2 class="mb-3 font-serif text-lg font-semibold">Episodes</h2>
        <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
            @foreach($episodes as $row)
                <li>
                    <a href="{{ route('episodes.show', [$story->slug, $row['model']->episode_number]) }}"
                       class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-stone-50">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ $row['model']->episode_number }}. {{ $row['model']->title }}
                            </p>
                            @if($row['progress'])
                                <p class="text-xs text-stone-400">{{ $row['progress'] }}% read</p>
                            @endif
                        </div>
                        @if($row['locked'])
                            <span class="shrink-0 rounded-full bg-stone-100 px-2 py-1 text-[11px] text-stone-500">
                                {{ $row['lock_reason'] === 'guest_limit' ? 'Sign in to read' : 'Premium' }}
                            </span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endsection

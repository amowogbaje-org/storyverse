@php
    $progressPercent = $progress[$story->id] ?? null;
    $storyIsLiked = $liked[$story->id] ?? false;
    $storyIsBookmarked = $bookmarked[$story->id] ?? false;
@endphp
<a href="{{ route('stories.show', $story->slug) }}" class="group block overflow-hidden rounded-xl border border-stone-200 bg-white transition hover:shadow-md">
    <div class="aspect-[3/4] w-full overflow-hidden bg-stone-100">
        @if($story->cover_image_url)
            <img src="{{ $story->cover_image_url }}" alt="{{ $story->title }}" loading="lazy"
                 class="h-full w-full object-cover transition group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center text-stone-300">
                <span class="font-serif text-3xl">{{ Str::substr($story->title, 0, 1) }}</span>
            </div>
        @endif
    </div>
    <div class="p-3">
        <h3 class="line-clamp-2 font-serif text-sm font-semibold leading-snug">{{ $story->title }}</h3>
        <p class="mt-1 text-xs text-stone-500">{{ $story->penName?->display_name ?? 'Unknown author' }}</p>
        <div class="mt-2 flex items-center gap-3 text-[11px] text-stone-400">
            <span>{{ number_format($story->views_count) }} reads</span>
            <span>{{ number_format($story->likes_count) }} likes</span>
            @if($story->access_type === 'premium')
                <span class="rounded-full bg-amber-100 px-1.5 py-0.5 text-amber-700">Premium</span>
            @endif
        </div>
        @if($progressPercent)
            <div class="mt-2 h-1 w-full overflow-hidden rounded-full bg-stone-100">
                <div class="h-full bg-stone-900" style="width: {{ min(100, $progressPercent) }}%"></div>
            </div>
        @endif
    </div>
</a>

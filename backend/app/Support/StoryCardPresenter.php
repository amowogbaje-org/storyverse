<?php

namespace App\Support;

use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoryCardPresenter
{
    public static function card(
        Story $story,
        ?User $user,
        ?Collection $progressByStory = null,
        ?Collection $likedByStory = null,
        ?Collection $bookmarkedByStory = null,
    ): array {
        $progressByStory ??= collect();

        // Batched maps are preferred (see likedMap()/bookmarkedMap() below) - pass
        // one in whenever you're rendering a list, or this silently falls back to
        // a per-card query, which is fine for a single story but an N+1 for a list.
        $isLiked = $likedByStory !== null
            ? (bool) ($likedByStory[$story->id] ?? false)
            : ($user ? $story->likes()->where('user_id', $user->id)->exists() : false);

        $isBookmarked = $bookmarkedByStory !== null
            ? (bool) ($bookmarkedByStory[$story->id] ?? false)
            : ($user ? $story->bookmarks()->where('user_id', $user->id)->exists() : false);

        return [
            'id' => $story->id,
            'title' => $story->title,
            'slug' => $story->slug,
            'description' => $story->description,
            'cover_image_url' => $story->cover_image_url,
            'access_type' => $story->access_type,
            'is_completed' => $story->is_completed,
            'views_count' => $story->views_count,
            'likes_count' => $story->likes_count,
            'bookmarks_count' => $story->bookmarks_count,
            'comments_count' => $story->comments_count,
            'category_name' => $story->category?->name,
            'author_display_name' => $story->penName?->display_name,
            'author_slug' => $story->penName?->slug,
            'reader_progress_percent' => isset($progressByStory[$story->id]) ? round($progressByStory[$story->id]) : null,
            'is_liked_by_user' => $isLiked,
            'is_bookmarked_by_user' => $isBookmarked,
        ];
    }

    /** Avg reading progress per story for one user, batched to avoid N+1 queries. */
    public static function progressMap(?User $user, Collection $storyIds): Collection
    {
        if (! $user || $storyIds->isEmpty()) {
            return collect();
        }

        return DB::table('reading_progress')
            ->where('user_id', $user->id)
            ->whereIn('story_id', $storyIds)
            ->selectRaw('story_id, avg(progress_percent) as avg_percent')
            ->groupBy('story_id')
            ->pluck('avg_percent', 'story_id');
    }

    /** story_id => true for every story in $storyIds this user has liked, batched to avoid N+1. */
    public static function likedMap(?User $user, Collection $storyIds): Collection
    {
        if (! $user || $storyIds->isEmpty()) {
            return collect();
        }

        return DB::table('story_likes')
            ->where('user_id', $user->id)
            ->whereIn('story_id', $storyIds)
            ->pluck('story_id')
            ->mapWithKeys(fn ($id) => [$id => true]);
    }

    /** story_id => true for every story in $storyIds this user has bookmarked, batched to avoid N+1. */
    public static function bookmarkedMap(?User $user, Collection $storyIds): Collection
    {
        if (! $user || $storyIds->isEmpty()) {
            return collect();
        }

        return DB::table('story_bookmarks')
            ->where('user_id', $user->id)
            ->whereIn('story_id', $storyIds)
            ->pluck('story_id')
            ->mapWithKeys(fn ($id) => [$id => true]);
    }
}

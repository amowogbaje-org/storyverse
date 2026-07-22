<?php

namespace App\Support;

use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoryCardPresenter
{
    public static function card(Story $story, ?User $user, ?Collection $progressByStory = null): array
    {
        $progressByStory ??= collect();

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
            'is_liked_by_user' => $user ? $story->likes()->where('user_id', $user->id)->exists() : false,
            'is_bookmarked_by_user' => $user ? $story->bookmarks()->where('user_id', $user->id)->exists() : false,
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
}

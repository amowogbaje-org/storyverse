<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Story extends Model
{
    protected $fillable = [
        'pen_name_id', 'category_id', 'title', 'slug', 'description',
        'cover_image_url', 'status', 'access_type', 'is_completed',
        'episodes_count', 'views_count', 'likes_count', 'bookmarks_count',
        'comments_count', 'shares_count', 'published_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function penName(): BelongsTo
    {
        return $this->belongsTo(PenName::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'genre_story');
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class)->orderBy('episode_number');
    }

    public function publishedEpisodes(): HasMany
    {
        return $this->episodes()->where('status', 'published');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(StoryLike::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(StoryBookmark::class);
    }
}

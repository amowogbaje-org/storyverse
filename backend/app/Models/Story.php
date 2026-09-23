<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Story extends Model
{
    protected $fillable = [
        'pen_name_id', 'title', 'slug', 'description',
        'cover_image_url', 'cover_image_thumb_url', 'status', 'access_type', 'is_completed',
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

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_story');
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

    public function purchases(): HasMany
    {
        return $this->hasMany(StoryPurchase::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(StoryPrice::class);
    }

    public function isPurchasable(): bool
    {
        return $this->relationLoaded('prices')
            ? $this->prices->isNotEmpty()
            : $this->prices()->exists();
    }

    /**
     * The price to actually show/charge a reader, resolved for their
     * currency - the author's own price in that exact currency if set, else
     * USD as the most likely "base" price, else whatever price does exist.
     * Falling all the way through to "whatever exists" (rather than null)
     * means a reader in a currency the author hasn't priced always sees a
     * real, purchasable price instead of the buy option just disappearing.
     */
    public function priceFor(?string $currency): ?StoryPrice
    {
        $prices = $this->relationLoaded('prices') ? $this->prices : $this->prices()->get();

        return $prices->firstWhere('currency', $currency)
            ?? $prices->firstWhere('currency', 'USD')
            ?? $prices->first();
    }
}

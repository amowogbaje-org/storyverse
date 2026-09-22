<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Episode extends Model
{
    protected $fillable = [
        'story_id', 'title', 'episode_number', 'content',
        'raw_content', 'raw_content_updated_at', 'styled_at', 'styling_attempts',
        'word_count', 'status', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'content_synced_at' => 'datetime',
        'raw_content_updated_at' => 'datetime',
        'styled_at' => 'datetime',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function readingProgress(): HasMany
    {
        return $this->hasMany(ReadingProgress::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}

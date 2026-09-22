<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenName extends Model
{
    protected $fillable = ["user_id", "display_name", "slug", "bio", "avatar_url", "is_default"];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    public function tips(): HasMany
    {
        return $this->hasMany(Tip::class);
    }
}

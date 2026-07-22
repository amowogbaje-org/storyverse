<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'icon_url', 'category', 'tier',
        'criteria_type', 'criteria_value', 'reward_type', 'reward_payload',
    ];

    protected $casts = [
        'reward_payload' => 'array',
    ];

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }
}

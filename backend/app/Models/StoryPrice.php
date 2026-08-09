<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryPrice extends Model
{
    protected $fillable = ['story_id', 'currency', 'amount'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tip extends Model
{
    protected $fillable = [
        'pen_name_id', 'user_id', 'amount', 'currency', 'gateway', 'gateway_reference', 'status', 'message',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function penName(): BelongsTo
    {
        return $this->belongsTo(PenName::class);
    }

    public function tipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    protected $fillable = [
        'user_id', 'period_start', 'period_end',
        'subscription_share_amount', 'story_sales_amount', 'tips_amount', 'total_amount', 'currency',
        'status', 'payout_account_name', 'payout_account_number', 'payout_bank_name', 'payout_bank_code',
        'gateway_transfer_id', 'failure_reason', 'paid_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

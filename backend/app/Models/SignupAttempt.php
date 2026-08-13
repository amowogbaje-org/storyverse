<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per signup attempt (native, google, or otp) - success or failure.
 * See AuthController::logSignupAttempt(), which is the only writer.
 */
class SignupAttempt extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'channel',
        'identifier',
        'status',
        'reason',
        'user_id',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

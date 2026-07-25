<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Manual mail-risk mitigation for as long as we're running on unauthenticated
 * SMTP with no bounce webhook: when a send to an address hard-bounces, an
 * admin (or the ad hoc GET endpoint) records it here so we stop trying to
 * mail it again and tell the person up front that the address looks invalid.
 */
class BlacklistedEmail extends Model
{
    protected $fillable = ['email', 'reason', 'blacklisted_by'];

    public static function isBlacklisted(string $email): bool
    {
        return static::where('email', mb_strtolower($email))->exists();
    }
}

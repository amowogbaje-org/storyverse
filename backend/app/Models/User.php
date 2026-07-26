<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        "name", "email", "password", "phone", "country_code", "google_id",
        "currency", "avatar_url", "role", "email_verified_at",
        "current_streak_days", "last_streak_date", "last_active_at", "notification_preferences",
        "referred_by", "referral_reward_tier_claimed",
    ];

    protected $hidden = ["password", "google_id"];

    protected $appends = ["display_name"];

    protected $casts = [
        "notification_preferences" => "array",
        "email_verified_at" => "datetime",
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (! $user->referral_code) {
                $user->referral_code = static::generateUniqueReferralCode();
            }
        });
    }

    /**
     * Short, shareable, and case-collision-safe: uppercase alphanumeric with
     * ambiguous characters (0/O, 1/I/L) removed, since this ends up in URLs
     * and typed-out invite codes where those are easy to misread.
     */
    private static function generateUniqueReferralCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $code = collect(range(1, 7))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * The frontend (and every other model's pen-name/author naming) standardizes on
     * display_name; this just aliases the reader's `name` column so responses are
     * consistent without a migration.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function penNames(): HasMany
    {
        return $this->hasMany(PenName::class);
    }

    public function readingProgress(): HasMany
    {
        return $this->hasMany(ReadingProgress::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function badges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /** Everyone this user has referred, verified or not - see verifiedReferrals() for the count that actually matters. */
    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function hasActivePremiumSubscription(): bool
    {
        return $this->subscriptions()
            ->where("status", "active")
            ->where("current_period_end", ">", now())
            ->exists();
    }
}

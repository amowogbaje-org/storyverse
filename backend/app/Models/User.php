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
        "timezone", "preferred_reading_time",
        "current_streak_days", "last_streak_date", "last_active_at", "notification_preferences",
        "referred_by", "referral_reward_tier_claimed",
        "payout_account_name", "payout_account_number", "payout_bank_name", "payout_bank_code",
    ];

    protected $hidden = ["password", "google_id"];

    protected $appends = ["display_name"];

    protected $casts = [
        "notification_preferences" => "array",
        "email_verified_at" => "datetime",
        "premium_access_until" => "datetime",
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

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
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

    /**
     * Falls back to UTC for anyone who hasn't had a timezone captured yet
     * (older accounts, or a client that hasn't sent one) - never throws on a
     * bad/missing value, since this feeds directly into schedule-matching.
     */
    public function resolvedTimezone(): string
    {
        if (! $this->timezone) {
            return 'UTC';
        }

        try {
            new \DateTimeZone($this->timezone);

            return $this->timezone;
        } catch (\Exception) {
            return 'UTC';
        }
    }

    /**
     * The local hour (0-23) reading-time-aware reminders should fire at: the
     * reader's own preferred_reading_time if they've set one, otherwise a
     * default evening slot (see SendReadingTimeReminders /
     * SendNewEpisodeDigest docblocks for how this is used).
     */
    public function notificationTargetHour(int $defaultEveningHour = 19): int
    {
        if ($this->preferred_reading_time) {
            return (int) \Carbon\Carbon::parse($this->preferred_reading_time)->format('G');
        }

        return $defaultEveningHour;
    }

    /** True when "now", converted to this user's timezone, falls in their target hour. */
    public function isCurrentlyInNotificationHour(int $defaultEveningHour = 19): bool
    {
        return now($this->resolvedTimezone())->hour === $this->notificationTargetHour($defaultEveningHour);
    }

    /**
     * @deprecated Subscriptions are no longer the premium-access mechanism
     * (stories are bought individually - see story_prices/StoryAccessService).
     * Kept only so historical Subscription rows remain queryable (author
     * payout/earnings history) and this doesn't become a hard error for any
     * code that still calls it. Use hasBonusPremiumAccess() for actual access
     * checks going forward.
     */
    public function hasActivePremiumSubscription(): bool
    {
        return $this->subscriptions()
            ->where("status", "active")
            ->where("current_period_end", ">", now())
            ->exists();
    }

    /** Temporary premium access from a reward (badge/referral), independent of any purchase - see BonusAccessService. */
    public function hasBonusPremiumAccess(): bool
    {
        return $this->premium_access_until !== null && $this->premium_access_until->isFuture();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        "name", "email", "password", "phone", "country_code", "google_id",
        "currency", "avatar_url", "role", "email_verified_at",
        "current_streak_days", "last_streak_date", "last_active_at",
    ];

    protected $hidden = ["password", "google_id"];

    protected $appends = ["display_name"];

    protected $casts = [
        "notification_preferences" => "array",
        "email_verified_at" => "datetime",
    ];

    /**
     * The frontend (and every other model's pen-name/author naming) standardizes on
     * display_name; this just aliases the reader's `name` column so responses are
     * consistent without a migration.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name;
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

    public function hasActivePremiumSubscription(): bool
    {
        return $this->subscriptions()
            ->where("status", "active")
            ->where("current_period_end", ">", now())
            ->exists();
    }
}

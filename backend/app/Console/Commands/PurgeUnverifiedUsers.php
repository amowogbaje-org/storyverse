<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Deletes accounts that registered but never completed OTP verification within
 * the grace period. Cascading foreign keys (reading_progress, likes, bookmarks,
 * comments, badges, activity events, subscriptions, payments, pen_names) clean
 * up automatically - every user_id FK in the schema uses cascadeOnDelete().
 */
class PurgeUnverifiedUsers extends Command
{
    protected $signature = 'app:purge-unverified-users {--hours=24 : Grace period before an unverified account is deleted}';

    protected $description = 'Delete accounts that never completed email/OTP verification within the grace period';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        $stale = User::whereNull('email_verified_at')
            ->where('created_at', '<', now()->subHours($hours))
            ->get();

        $count = $stale->count();

        foreach ($stale as $user) {
            $user->delete();
        }

        $this->info("Purged {$count} unverified account(s) older than {$hours}h.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\WeMissedYou;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Readers who've gone quiet for a week get a nudge - at most once a week each
 * (the cooldown below), so someone who's been away for a month doesn't get
 * hit with a backlog of "we missed you"s the moment this job notices them.
 */
class SendWeMissedYouNotifications extends Command
{
    protected $signature = 'app:send-we-missed-you-notifications {--inactive-days=7 : How many days of inactivity before sending}';

    protected $description = 'Notify readers who have gone quiet for a while';

    private const COOLDOWN_DAYS = 7;

    public function handle(): int
    {
        $inactiveDays = (int) $this->option('inactive-days');

        $quiet = User::whereNotNull('email_verified_at')
            ->whereNotNull('last_active_at')
            ->where('last_active_at', '<', now()->subDays($inactiveDays))
            ->get();

        $sent = 0;

        foreach ($quiet as $user) {
            $cooldownKey = "notif:missed_you:{$user->id}";

            if (Cache::has($cooldownKey)) {
                continue;
            }

            $user->notify(new WeMissedYou());
            Cache::put($cooldownKey, true, now()->addDays(self::COOLDOWN_DAYS));
            $sent++;
        }

        $this->info("Sent {$sent} 'we missed you' notification(s).");

        return self::SUCCESS;
    }
}

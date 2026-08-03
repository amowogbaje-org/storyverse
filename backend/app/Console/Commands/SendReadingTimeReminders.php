<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Story;
use App\Models\User;
use App\Notifications\ContinueReading;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * For readers who've set a preferred_reading_time in Settings: fire the
 * "continue where you left off" reminder right around that time, in their
 * own timezone, rather than on a fixed platform-wide schedule.
 *
 * Runs hourly (see routes/console.php). Each run only notifies users whose
 * *local* clock is currently in the same hour as their preferred_reading_time
 * (User::isCurrentlyInNotificationHour) - so across 24 runs a day, everyone
 * with a set time gets caught within an hour of it, and nobody gets more
 * than one per day thanks to the cooldown below.
 *
 * Readers without a preferred_reading_time are handled by
 * SendContinueReadingReminders instead, on a simple idle-hours basis.
 */
class SendReadingTimeReminders extends Command
{
    protected $signature = 'app:send-reading-time-reminders';

    protected $description = "Remind readers to resume their book at their own chosen reading time";

    private const COOLDOWN_HOURS = 20;

    public function handle(): int
    {
        $users = User::whereNotNull('preferred_reading_time')
            ->whereNotNull('email_verified_at')
            ->get()
            ->filter(fn (User $u) => $u->isCurrentlyInNotificationHour());

        $sent = 0;

        foreach ($users as $user) {
            $cooldownKey = "notif:reading_time:{$user->id}";

            if (Cache::has($cooldownKey)) {
                continue;
            }

            $row = DB::table('reading_progress')
                ->where('user_id', $user->id)
                ->whereBetween('progress_percent', [1, 95])
                ->orderByDesc('last_read_at')
                ->first();

            if (! $row) {
                continue;
            }

            $episode = Episode::find($row->episode_id);
            $story = $episode ? Story::find($row->story_id) : null;

            if (! $episode || ! $story) {
                continue;
            }

            $user->notify(new ContinueReading($story, $episode, (int) round($row->progress_percent)));
            Cache::put($cooldownKey, true, now()->addHours(self::COOLDOWN_HOURS));
            $sent++;
        }

        $this->info("Sent {$sent} reading-time reminder(s).");

        return self::SUCCESS;
    }
}

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
 * Reminds readers about a story they're partway through and haven't touched
 * in a few days - not so soon that it feels impatient, not so late the
 * moment's long gone. One reminder per user per run (their single most
 * recently-read in-progress story), and a cooldown so this doesn't repeat
 * daily for someone who just hasn't gotten back to it yet.
 */
class SendContinueReadingReminders extends Command
{
    protected $signature = 'app:send-continue-reading-reminders';

    protected $description = 'Remind readers to continue a story they left partway through';

    private const MIN_IDLE_DAYS = 2;
    private const MAX_IDLE_DAYS = 7;
    private const COOLDOWN_DAYS = 5;

    public function handle(): int
    {
        $candidates = DB::table('reading_progress')
            ->select('user_id', 'story_id', 'episode_id', 'progress_percent', 'last_read_at')
            ->whereBetween('progress_percent', [5, 95])
            ->whereBetween('last_read_at', [now()->subDays(self::MAX_IDLE_DAYS), now()->subDays(self::MIN_IDLE_DAYS)])
            ->orderByDesc('last_read_at')
            ->get()
            ->unique('user_id'); // most recent per user, since it's already ordered desc

        $sent = 0;

        foreach ($candidates as $row) {
            $cooldownKey = "notif:continue:{$row->user_id}";

            if (Cache::has($cooldownKey)) {
                continue;
            }

            $user = User::find($row->user_id);
            $episode = Episode::find($row->episode_id);
            $story = $episode ? Story::find($row->story_id) : null;

            if (! $user || ! $episode || ! $story) {
                continue;
            }

            $user->notify(new ContinueReading($story, $episode, (int) round($row->progress_percent)));
            Cache::put($cooldownKey, true, now()->addDays(self::COOLDOWN_DAYS));
            $sent++;
        }

        $this->info("Sent {$sent} continue-reading reminder(s).");

        return self::SUCCESS;
    }
}

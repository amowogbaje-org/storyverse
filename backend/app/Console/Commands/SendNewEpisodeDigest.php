<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Story;
use App\Models\User;
use App\Notifications\NewEpisodesAvailable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tells a reader when a story they're actively reading has published new
 * episodes - deliberately a once-a-day digest, not one push per publish
 * event, so an author who drops five episodes in an afternoon doesn't spam
 * their readers five times.
 *
 * Runs hourly (see routes/console.php), but each reader is only actually
 * notified during the local hour that matches their preferred_reading_time,
 * or a default evening slot (19:00) in their timezone if they haven't set
 * one - see User::isCurrentlyInNotificationHour(). The per-(user, story)
 * cooldown below is what keeps this to once a day even though the command
 * itself runs every hour.
 */
class SendNewEpisodeDigest extends Command
{
    protected $signature = 'app:send-new-episode-digest';

    protected $description = "Notify readers about new episodes on stories they're reading, timed to their reading time";

    // Slightly over 24h so an episode published near a run boundary isn't
    // missed by the next day's window - the per-user cooldown still caps
    // this to one notification a day regardless.
    private const LOOKBACK_HOURS = 25;

    private const COOLDOWN_HOURS = 20;

    public function handle(): int
    {
        $recentEpisodesByStory = Episode::where('status', 'published')
            ->where('published_at', '>=', now()->subHours(self::LOOKBACK_HOURS))
            ->get()
            ->groupBy('story_id');

        if ($recentEpisodesByStory->isEmpty()) {
            $this->info('No recently-published episodes to notify about.');

            return self::SUCCESS;
        }

        // Readers actively engaged with any of these stories - anyone with
        // reading progress logged on it, regardless of how far they got.
        $readerRows = DB::table('reading_progress')
            ->whereIn('story_id', $recentEpisodesByStory->keys())
            ->select('user_id', 'story_id')
            ->distinct()
            ->get()
            ->groupBy('user_id');

        $sent = 0;

        foreach ($readerRows as $userId => $rows) {
            $user = User::find($userId);

            if (! $user || ! $user->isCurrentlyInNotificationHour()) {
                continue;
            }

            foreach ($rows as $row) {
                $cooldownKey = "notif:new_episode:{$user->id}:{$row->story_id}";

                if (Cache::has($cooldownKey)) {
                    continue;
                }

                $episodes = $recentEpisodesByStory->get($row->story_id);
                $story = $episodes ? Story::find($row->story_id) : null;

                if (! $story || ! $episodes || $episodes->isEmpty()) {
                    continue;
                }

                $user->notify(new NewEpisodesAvailable($story, $episodes->count()));
                Cache::put($cooldownKey, true, now()->addHours(self::COOLDOWN_HOURS));
                $sent++;
            }
        }

        $this->info("Sent {$sent} new-episode digest notification(s).");

        return self::SUCCESS;
    }
}

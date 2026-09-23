<?php

namespace App\Providers;

use App\Events\UserActivityLogged;
use App\Listeners\AwardBadgesListener;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Laravel's event auto-discovery would normally pick this up on its own,
        // but registering explicitly here so it's obvious where the badge engine
        // actually gets wired in without having to go hunting for it.
        Event::listen(UserActivityLogged::class, AwardBadgesListener::class);

        $this->logSlowQueries();
        $this->defineTelescopeKeyRateLimiter();
    }

    /**
     * config/telescope.php previously pointed straight at 'throttle:20,1',
     * which applies that 20/minute cap to *every* Telescope route - including
     * /telescope-api/telescope-entries, which Telescope's own dashboard JS
     * polls every couple of seconds while the tab is open. That alone blows
     * through 20 requests/minute almost immediately, which is exactly what
     * "Telescope stopped listening for new entries. The server returned a
     * 429 response." is - the dashboard reporting its own polling got
     * throttled, not a real attack.
     *
     * The 20/min cap is the documented defense for TELESCOPE_ACCESS_KEY
     * being an intentionally short 4-digit PIN (see .env.example) - so the
     * fix isn't to just raise the number, which would weaken that on
     * purpose-built protection. It's to only apply it to actual key-guessing
     * attempts (requests with no unlocked session yet, or an explicit
     * ?key=... query param) and let an already-unlocked browser session poll
     * freely, same as before this existed.
     */
    private function defineTelescopeKeyRateLimiter(): void
    {
        RateLimiter::for('telescope-key', function (Request $request) {
            $alreadyUnlocked = $request->session()->get('telescope_unlocked') === true
                && $request->query('key') === null;

            return $alreadyUnlocked
                ? Limit::none()
                : Limit::perMinute(20)->by($request->ip());
        });
    }

    /**
     * Zero-dependency companion to Telescope (see the Telescope setup notes
     * in .env.example) - this works today, on every request AND every
     * console command/queue job, without needing composer install to run
     * first. Logs any single query slower than the threshold to the
     * dedicated 'performance' channel (config/logging.php) - grep that log
     * for "slow query" to find exactly which queries are worth indexing or
     * rewriting, without needing a UI at all.
     *
     * Deliberately not logging every query (that's what Telescope's for) -
     * just the slow ones, so this has near-zero overhead in the common case
     * and doesn't fill the log with noise.
     */
    private function logSlowQueries(): void
    {
        $thresholdMs = (int) config('logging.slow_query_threshold_ms', 200);

        DB::listen(function ($query) use ($thresholdMs) {
            if ($query->time < $thresholdMs) {
                return;
            }

            Log::channel('performance')->warning('slow query', [
                'ms' => $query->time,
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'connection' => $query->connectionName,
            ]);
        });
    }
}

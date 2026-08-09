<?php

namespace App\Providers;

use App\Events\UserActivityLogged;
use App\Listeners\AwardBadgesListener;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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

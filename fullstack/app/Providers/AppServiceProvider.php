<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->logSlowQueries();
    }

    /**
     * Zero-dependency companion to Telescope: works on every request AND
     * every console command, no UI needed. Logs any single query slower
     * than the threshold to the 'performance' channel - grep that log for
     * "slow query" to find exactly what's worth indexing.
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

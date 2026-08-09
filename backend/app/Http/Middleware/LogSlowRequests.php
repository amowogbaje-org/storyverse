<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Logs any request slower than logging.slow_request_threshold_ms (default
 * 1000ms) to the 'performance' log channel, with enough context to actually
 * act on it: which route, how long, how many queries ran, and who was
 * signed in. See AppServiceProvider::logSlowQueries() for the per-query
 * counterpart - together these answer "is this endpoint slow because of one
 * bad query, or because it's doing 200 small ones" without needing Telescope
 * installed at all.
 *
 * Query counting uses DB::listen + a counter rather than
 * DB::enableQueryLog()/getQueryLog(), which keeps every query's full SQL and
 * bindings in memory for the life of the request - fine for local debugging,
 * real overhead to carry on every single production request just to get a
 * count.
 */
class LogSlowRequests
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $queryCount = 0;

        // Note: DB::listen has no corresponding "unlisten" - this closure
        // lives for the connection's lifetime. Harmless under classic
        // PHP-FPM (a fresh process per request means nothing accumulates),
        // but would leak a listener per request under Octane/a long-running
        // worker - switch to a counter incremented via a single
        // boot-time listener (see AppServiceProvider) instead if this ever
        // moves to Octane.
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $response = $next($request);

        $durationMs = (int) ((microtime(true) - $start) * 1000);
        $thresholdMs = (int) config('logging.slow_request_threshold_ms', 1000);

        if ($durationMs >= $thresholdMs) {
            Log::channel('performance')->warning('slow request', [
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $request->route()?->getName() ?? $request->route()?->uri(),
                'ms' => $durationMs,
                'query_count' => $queryCount,
                'status' => $response->getStatusCode(),
                'user_id' => $request->attributes->get('auth_user')?->id,
            ]);
        }

        return $response;
    }
}

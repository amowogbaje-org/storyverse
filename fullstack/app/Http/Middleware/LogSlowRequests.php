<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Logs any request slower than logging.slow_request_threshold_ms (default
 * 1000ms) to the 'performance' log channel: which route, how long, how many
 * queries ran, who was signed in. Answers "is this page slow because of one
 * bad query, or 200 small ones" without needing Telescope open at the time.
 */
class LogSlowRequests
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $queryCount = 0;

        // No corresponding "unlisten" - harmless under classic PHP-FPM (a
        // fresh process per request), would leak under Octane.
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
                'user_id' => $request->user()?->id,
            ]);
        }

        return $response;
    }
}

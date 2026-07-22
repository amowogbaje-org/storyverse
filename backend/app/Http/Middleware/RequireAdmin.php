<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Stack on top of jwt.auth (needs a resolved user in the request already).
 * Used for the admin panel's own API surface (analytics, story/episode
 * management, etc.) — anything a reader shouldn't be able to call.
 */
class RequireAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->attributes->get('auth_user');

        if (! $user || $user->role !== 'admin') {
            return response()->json([
                'error' => ['code' => 'forbidden', 'message' => 'Admin access required.'],
            ], 403);
        }

        return $next($request);
    }
}

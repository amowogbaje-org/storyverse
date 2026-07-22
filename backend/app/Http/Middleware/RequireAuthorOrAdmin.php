<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Content management (stories/episodes/pen names) is open to both 'author' and
 * 'admin' roles — authors manage their own stories, admins can manage anyone's.
 * Scoping to "own" content happens per-controller, not here.
 */
class RequireAuthorOrAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->attributes->get('auth_user');

        if (! $user || ! in_array($user->role, ['author', 'admin'], true)) {
            return response()->json([
                'error' => ['code' => 'forbidden', 'message' => 'Author or admin access required.'],
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Content management (stories/episodes/pen names) is open to both 'author'
 * and 'admin' - authors manage their own, admins can manage anyone's.
 * Scoping to "own" content happens per-controller, not here.
 */
class RequireAuthor
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['author', 'admin'], true)) {
            abort(403, 'Author or admin access required.');
        }

        return $next($request);
    }
}

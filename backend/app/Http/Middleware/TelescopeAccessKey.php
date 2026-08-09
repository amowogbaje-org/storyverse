<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Replaces Telescope's default Authorize middleware (config/telescope.php's
 * `middleware` array) in this app - that default checks Auth::user() against
 * the 'viewTelescope' gate (see TelescopeServiceProvider::gate), which
 * assumes a session-authenticated web-guard user. This app has no such
 * thing: every request authenticates via a JWT bearer token read by
 * JwtAuthenticate, not a browser session, so Auth::user() is always null for
 * a plain browser visit to /telescope and the gate can never pass.
 *
 * Visit /telescope?key=<TELESCOPE_ACCESS_KEY> once - the key unlocks the
 * session (not stored in a cookie itself, just a boolean flag), and the URL
 * immediately redirects to strip the key back out of the address bar so it
 * doesn't linger in browser history for that tab. You'll need to repeat this
 * whenever the session ends (browser closed, session lifetime expires, etc).
 */
class TelescopeAccessKey
{
    public function handle(Request $request, Closure $next)
    {
        $configuredKey = config('services.telescope_access_key');

        if (! $configuredKey) {
            abort(403, 'Telescope access is not configured - set TELESCOPE_ACCESS_KEY in .env.');
        }

        $providedKey = $request->query('key');

        if ($providedKey !== null && hash_equals($configuredKey, $providedKey)) {
            $request->session()->put('telescope_unlocked', true);

            // Strip ?key=... from the URL before rendering anything, so the
            // secret doesn't sit in the address bar / browser history beyond
            // this one redirect.
            return redirect($request->url());
        }

        if ($request->session()->get('telescope_unlocked') === true) {
            return $next($request);
        }

        abort(403, 'Add ?key=<TELESCOPE_ACCESS_KEY> to the URL once to unlock Telescope for this browser session.');
    }
}

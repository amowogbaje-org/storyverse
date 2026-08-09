<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * NOT registered in bootstrap/providers.php yet - see the "Setting up
 * Telescope" section in README.md. Referencing Laravel\Telescope classes
 * before `composer install` has actually fetched the package would break
 * every artisan command with a "class not found" error, so this file exists
 * ready to go but stays inert until you add it to bootstrap/providers.php
 * yourself, after installing.
 */
class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal
                || $entry->isReportableException()
                || $entry->isFailedRequest()
                || $entry->isFailedJob()
                || $entry->isScheduledTask()
                || $entry->hasMonitoredTag();
        });
    }

    private function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation']);
        Telescope::hideRequestHeaders([
            'cookie', 'x-csrf-token', 'x-xsrf-token', 'authorization',
        ]);
    }

    /**
     * Who can open /telescope outside local, via Laravel's own Auth/Gate
     * system - gated on the same 'admin' role every other admin-only
     * endpoint in this app uses (see RequireAdmin middleware).
     *
     * NOT actually consulted if you've swapped config/telescope.php's
     * `middleware` entry for App\Http\Middleware\TelescopeAccessKey (see
     * MONITORING.md) - that replaces Telescope's default Authorize
     * middleware, which is the thing that calls this gate in the first
     * place. This app has no session-based login for Auth::user() to
     * resolve here, so the passkey approach is what's actually in use.
     * Left in place in case you ever add real session-based admin login and
     * want to switch back to role-based gating instead.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (User $user) {
            return $user->role === 'admin';
        });
    }
}

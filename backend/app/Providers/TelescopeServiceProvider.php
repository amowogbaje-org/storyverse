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
     * Who can open /telescope outside local. Gated on the same 'admin' role
     * every other admin-only endpoint in this app uses (see RequireAdmin
     * middleware) rather than Telescope's own default example (a hardcoded
     * list of email addresses) - one less place to remember to update when
     * admin access changes.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (User $user) {
            return $user->role === 'admin';
        });
    }
}

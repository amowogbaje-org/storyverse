<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Telescope's entry tables grow unbounded otherwise - keeps 48 hours.
if (class_exists(\Laravel\Telescope\Telescope::class)) {
    Schedule::command('telescope:prune --hours=48')->daily();
}

// Everything below existed in the old backend but its command class wasn't
// ported in this pass (see fullstack/README.md "What's intentionally left
// out") - uncomment once the matching app/Console/Commands/*.php exists:
//
// Schedule::command('app:purge-unverified-users')->daily();
// Schedule::command('app:send-analytics-summary')->dailyAt('08:00');
// Schedule::command('app:style-episodes')->everyFifteenMinutes();
// Schedule::command('app:send-new-story-recommendations')->dailyAt('09:00');
// Schedule::command('app:send-we-missed-you-notifications')->dailyAt('09:30');
// Schedule::command('app:send-continue-reading-reminders')->hourly();
// Schedule::command('app:send-reading-time-reminders')->hourly();
// Schedule::command('app:send-new-episode-digest')->hourly();
// Schedule::command('app:generate-monthly-payouts')->monthlyOn(2, '02:00');

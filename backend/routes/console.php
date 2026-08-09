<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Accounts that never verify their email get removed (with all related data,
// via cascading FKs) 24h after registration - see PurgeUnverifiedUsers.
Schedule::command('app:purge-unverified-users')->daily();

// Daily analytics summary email - see SendAnalyticsSummary.
Schedule::command('app:send-analytics-summary')->dailyAt('08:00');

// AI styling pass over freshly written/edited episodes, 10 at a time - see
// StyleEpisodes. 15 minutes keeps the delay between an author saving an
// episode and it appearing styled to readers short, without calling the AI
// provider so often that cost/rate-limits become a concern.
Schedule::command('app:style-episodes')->everyFifteenMinutes();

// Telescope's entry tables grow unbounded otherwise - keeps 48 hours, which
// is enough to investigate anything reported same-day or the morning after.
// Guarded so this is a no-op (not an error) before Telescope is installed -
// see the README's "Setting up Telescope" section.
if (class_exists(\Laravel\Telescope\Telescope::class)) {
    Schedule::command('telescope:prune --hours=48')->daily();
}

// Re-engagement notifications (in-app + push) - staggered so they don't all
// hit the DB/push provider at once. See each command's docblock for the
// rate-limiting/randomization that keeps these from feeling spammy.
Schedule::command('app:send-new-story-recommendations')->dailyAt('09:00');
Schedule::command('app:send-we-missed-you-notifications')->dailyAt('09:30');

// These three run hourly because each one gates its own actual send time
// per-user (idle hours, or the reader's own timezone/reading time) - the
// hourly cadence is just how often we check whether "now" is the right
// moment for any given reader, not how often any one reader gets notified.
// Per-user cooldowns inside each command still cap it to one send a day.
Schedule::command('app:send-continue-reading-reminders')->hourly();
Schedule::command('app:send-reading-time-reminders')->hourly();
Schedule::command('app:send-new-episode-digest')->hourly();

// A day after month-end, not exactly on the 1st, so last month's data
// (a renewal or a completed read logged in its final minutes) has settled.
// See GenerateMonthlyPayouts's docblock for the full reasoning.
Schedule::command('app:generate-monthly-payouts')->monthlyOn(2, '02:00');

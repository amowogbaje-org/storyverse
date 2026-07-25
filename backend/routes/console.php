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

// Re-engagement notifications (in-app + push) - staggered so they don't all
// hit the DB/push provider at once. See each command's docblock for the
// rate-limiting/randomization that keeps these from feeling spammy.
Schedule::command('app:send-new-story-recommendations')->dailyAt('09:00');
Schedule::command('app:send-continue-reading-reminders')->dailyAt('09:15');
Schedule::command('app:send-we-missed-you-notifications')->dailyAt('09:30');

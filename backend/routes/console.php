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

<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AnalyticsSummaryNotification;
use App\Services\AnalyticsQueryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Emails a daily digest (page visits, registrations, likes, reads, etc.) to
 * every admin account, plus any address in ANALYTICS_SUMMARY_EMAILS - useful
 * while there's no dedicated analytics-alerting tool yet. Scheduled daily at
 * 08:00 in routes/console.php.
 */
class SendAnalyticsSummary extends Command
{
    protected $signature = 'app:send-analytics-summary';

    protected $description = 'Email the last-24-hours analytics summary to admins';

    public function handle(AnalyticsQueryService $analytics): int
    {
        $counts = $analytics->last24Hours();
        $totals = $analytics->overview()['totals'];

        $recipients = User::where('role', 'admin')->pluck('email')->all();

        $extra = array_filter(array_map('trim', explode(',', (string) config('services.analytics.summary_emails'))));
        $recipients = array_unique(array_merge($recipients, $extra));

        if (empty($recipients)) {
            $this->warn('No admin or configured recipients found - skipping.');

            return self::SUCCESS;
        }

        Notification::route('mail', $recipients)->notify(new AnalyticsSummaryNotification($counts, $totals));

        $this->info('Sent analytics summary to: '.implode(', ', $recipients));

        return self::SUCCESS;
    }
}

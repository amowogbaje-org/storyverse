<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnalyticsSummaryNotification extends Notification
{
    use Queueable;

    /** @param array<string,int> $counts last24Hours() from AnalyticsQueryService */
    public function __construct(private readonly array $counts, private readonly array $totals) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Storyverse — daily analytics summary')
            ->greeting('Yesterday on Storyverse:')
            ->line("New signups: {$this->counts['new_users']}")
            ->line("Page views: {$this->counts['page_views']}")
            ->line("Episode reads started: {$this->counts['reads']}")
            ->line("Episode reads completed: {$this->counts['completed_reads']}")
            ->line("Likes: {$this->counts['likes']}")
            ->line("Bookmarks: {$this->counts['bookmarks']}")
            ->line("Comments: {$this->counts['comments']}")
            ->line("New subscriptions: {$this->counts['new_subscriptions']}")
            ->line('')
            ->line("All-time users: {$this->totals['users']}, published stories: {$this->totals['published_stories']}, active subscriptions: {$this->totals['active_subscriptions']}.")
            ->action('Open the analytics dashboard', config('app.frontend_url').'/admin/analytics');

        return $mail;
    }
}

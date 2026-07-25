<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WeMissedYou extends Notification
{
    use Queueable;

    private const LINES = [
        "We miss you! Your bookmarked stories are waiting.",
        "It's quiet without you. Come back and pick up where you left off?",
        "Your library missed you this week.",
        "A lot has updated since you last visited - come see what's new.",
    ];

    private string $body;

    public function __construct()
    {
        $this->body = self::LINES[array_rand(self::LINES)];
    }

    public function via($notifiable): array
    {
        if (! (($notifiable->notification_preferences ?? [])['missed_you_push'] ?? true)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'we_missed_you',
            'title' => 'We missed you 👋',
            'body' => $this->body,
            'url' => '/',
        ];
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => 'We missed you 👋',
            'body' => $this->body,
            'url' => '/',
        ];
    }
}

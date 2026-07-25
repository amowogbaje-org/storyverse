<?php

namespace App\Channels;

use App\Services\WebPushService;
use Illuminate\Notifications\Notification;

/**
 * Laravel resolves a channel referenced in a Notification's via() either from
 * the channel manager or, if given a class name directly, straight out of the
 * container and calls send() - no registration needed, just add
 * `\App\Channels\WebPushChannel::class` to via() and implement
 * toWebPush(): array{title, body, url?, icon?} on the notification, same
 * pattern as toMail().
 */
class WebPushChannel
{
    public function __construct(private WebPushService $webPush) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        if ((($notifiable->notification_preferences ?? [])['push_enabled'] ?? true) === false) {
            return;
        }

        $this->webPush->sendToUser($notifiable, $notification->toWebPush($notifiable));
    }
}

<?php

namespace App\Channels;

use App\Services\WebPushService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

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

        $result = $this->webPush->sendToUser($notifiable, $notification->toWebPush($notifiable));

        // "No subscription" is the ordinary, expected case for anyone who's never
        // turned push on - not worth logging every time. Anything else (missing
        // VAPID config, an actual delivery failure) is worth a low-volume trace,
        // since this channel has no other way to surface it - see
        // PushSubscriptionController::test() for the on-demand, user-facing version.
        if ($result['sent'] === 0 && $result['failed'] > 0) {
            Log::info('WebPushChannel: notification not delivered', [
                'user_id' => $notifiable->id,
                'notification' => $notification::class,
                'errors' => $result['errors'],
            ]);
        }
    }
}

<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    /**
     * Sends to every device/browser this user has subscribed from. A single
     * user with the app open on their phone and laptop gets it on both -
     * that's the point of storing one row per subscription rather than one
     * per user.
     *
     * @param array{title:string, body:string, url?:string, icon?:string} $payload
     */
    public function sendToUser(User $user, array $payload): void
    {
        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty() || ! config('webpush.public_key')) {
            return;
        }

        $webPush = $this->client();
        $subscriptionsById = [];

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->public_key,
                    'authToken' => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding,
                ]),
                json_encode($payload)
            );
            $subscriptionsById[$sub->endpoint] = $sub;
        }

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();

            if ($report->isSuccess()) {
                continue;
            }

            // 404/410 = the browser/OS says this subscription is gone for good
            // (user revoked permission, uninstalled, cleared data) - stop trying
            // and delete it, or it just piles up as permanent dead weight.
            if (in_array($report->getResponse()?->getStatusCode(), [404, 410], true)) {
                $subscriptionsById[$endpoint]?->delete();
            } else {
                Log::warning('Push notification failed', [
                    'endpoint' => $endpoint,
                    'reason' => $report->getReason(),
                ]);
            }
        }
    }

    private function client(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => config('webpush.subject'),
                'publicKey' => config('webpush.public_key'),
                'privateKey' => config('webpush.private_key'),
            ],
        ]);
    }
}

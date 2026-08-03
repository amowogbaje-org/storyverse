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
     * Silent no-ops (empty subscriptions, missing VAPID config) used to
     * return void and leave whoever called this with no way to tell success
     * from "nothing happened for a reason you can't see" - which is exactly
     * why "I got in-app and email but no push" is hard to self-diagnose.
     * Returning a summary lets PushSubscriptionController::test() explain
     * exactly what's missing instead of just shrugging.
     *
     * @param array{title:string, body:string, url?:string, icon?:string} $payload
     * @return array{sent:int, failed:int, pruned:int, errors:string[]}
     */
    public function sendToUser(User $user, array $payload): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'pruned' => 0, 'errors' => []];

        if (! config('webpush.public_key') || ! config('webpush.private_key')) {
            $result['errors'][] = 'Server has no VAPID keys configured (VAPID_PUBLIC_KEY/VAPID_PRIVATE_KEY missing from backend .env) - push cannot be sent to anyone until this is set.';

            return $result;
        }

        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            $result['errors'][] = 'This account has no active push subscription - the "Turn on" toggle in Settings has never been completed successfully on any device.';

            return $result;
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
                $result['sent']++;

                continue;
            }

            $result['failed']++;

            // 404/410 = the browser/OS says this subscription is gone for good
            // (user revoked permission, uninstalled, cleared data) - stop trying
            // and delete it, or it just piles up as permanent dead weight.
            if (in_array($report->getResponse()?->getStatusCode(), [404, 410], true)) {
                $subscriptionsById[$endpoint]?->delete();
                $result['pruned']++;
                $result['errors'][] = 'A subscription had expired or been revoked (browser/OS said gone) and has been removed - turn push back on to re-subscribe.';
            } else {
                $reason = $report->getReason();
                $result['errors'][] = $reason ?: 'Unknown delivery failure.';
                Log::warning('Push notification failed', ['endpoint' => $endpoint, 'reason' => $reason]);
            }
        }

        return $result;
    }

    private function client(): WebPush
    {
        // minishlink/web-push calls trigger_error() (E_USER_NOTICE) when
        // neither the GMP nor BCMath PHP extension is installed - it's just
        // telling you it picked a slower pure-PHP fallback for the crypto
        // math, not a real failure. Laravel's default error handler
        // escalates *any* PHP notice into a thrown ErrorException though, so
        // without suppressing this specific one, every push send would 500
        // on a server missing those extensions even though nothing is
        // actually broken. @ is deliberate and scoped to just this call.
        //
        // Proper long-term fix: enable the bcmath (or gmp) PHP extension on
        // this server - on cPanel hosting that's usually Software > "Select
        // PHP Version" > Extensions > check "bcmath" > Save, no code or
        // deploy needed. Once it's on, this notice stops firing on its own.
        return @new WebPush([
            'VAPID' => [
                'subject' => config('webpush.subject'),
                'publicKey' => config('webpush.public_key'),
                'privateKey' => config('webpush.private_key'),
            ],
        ]);
    }
}

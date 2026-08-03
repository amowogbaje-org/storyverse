<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $user = $this->requireUser($request);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])],
            [
                'user_id' => $user->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
            ]
        );

        return $this->ok(['subscribed' => true], 201);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['endpoint' => ['required', 'string']]);
        $user = $this->requireUser($request);

        $user->pushSubscriptions()
            ->where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))
            ->delete();

        return $this->ok(['subscribed' => false]);
    }

    /**
     * On-demand "does push actually reach me right now" check - the whole point
     * being: if it doesn't, this says *why* (no VAPID keys on the server, no
     * subscription on this account, or an actual delivery failure per
     * subscription) instead of the silent nothing you'd otherwise be debugging.
     *
     * Scoped entirely to $user's own subscriptions (via requireUser below) -
     * there's no cross-account action here, so this is safe for any signed-in
     * reader to use, which matches the Settings page already showing this
     * button to everyone regardless of role.
     */
    public function test(Request $request, WebPushService $webPush)
    {
        $user = $this->requireUser($request);

        $subscriptionCount = $user->pushSubscriptions()->count();

        $result = $webPush->sendToUser($user, [
            'title' => 'Test notification 🔔',
            'body' => 'If you can see this, push notifications are working end to end.',
            'url' => '/profile',
        ]);

        if ($result['sent'] > 0) {
            return $this->ok([
                'delivered' => true,
                'message' => "Sent to {$result['sent']} of {$subscriptionCount} subscribed device(s)."
                    .($result['failed'] ? " {$result['failed']} other device(s) failed - see details." : ''),
                'details' => $result,
            ]);
        }

        return response()->json([
            'error' => [
                'code' => 'push_not_delivered',
                'message' => $result['errors'][0] ?? "Couldn't deliver to any subscription.",
            ],
            'details' => $result,
        ], 422);
    }
}

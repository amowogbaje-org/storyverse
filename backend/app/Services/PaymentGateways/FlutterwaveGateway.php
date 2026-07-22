<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGateway;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\CheckoutSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FlutterwaveGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'flutterwave';
    }

    public function createCheckoutSession(User $user, SubscriptionPlan $plan): CheckoutSession
    {
        $txRef = 'sv_'.Str::uuid();

        $response = Http::withToken(config('services.flutterwave.secret_key'))
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref' => $txRef,
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'redirect_url' => config('app.frontend_url').'/subscription?status=success',
                'customer' => ['email' => $user->email, 'name' => $user->name],
                'meta' => ['user_id' => $user->id, 'plan_id' => $plan->id],
            ])
            ->throw()
            ->json();

        return new CheckoutSession($response['data']['link'], $txRef);
    }

    public function verifySignature(Request $request): bool
    {
        $expected = config('services.flutterwave.webhook_hash');

        // Flutterwave uses a static shared secret you set in their dashboard, not an
        // HMAC of the payload - a plain constant-time string comparison is correct here.
        return $expected && hash_equals($expected, (string) $request->header('verif-hash'));
    }

    public function extractSuccessfulReference(Request $request): ?string
    {
        $payload = $request->json()->all();

        if (($payload['data']['status'] ?? null) !== 'successful') {
            return null;
        }

        return $payload['data']['tx_ref'] ?? null;
    }
}

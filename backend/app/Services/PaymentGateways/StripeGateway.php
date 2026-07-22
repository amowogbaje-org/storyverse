<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGateway;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\CheckoutSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class StripeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'stripe';
    }

    public function createCheckoutSession(User $user, SubscriptionPlan $plan): CheckoutSession
    {
        $response = Http::asForm()
            ->withToken(config('services.stripe.secret'))
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment', // one-off charge per period; swap to 'subscription' + a Stripe Price ID for native recurring billing
                'customer_email' => $user->email,
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($plan->currency),
                        'product_data' => ['name' => "Storyverse {$plan->name}"],
                        'unit_amount' => (int) round($plan->price * 100),
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => config('app.frontend_url').'/subscription?status=success',
                'cancel_url' => config('app.frontend_url').'/subscription?status=cancelled',
                'metadata' => ['user_id' => $user->id, 'plan_id' => $plan->id],
            ])
            ->throw()
            ->json();

        return new CheckoutSession($response['url'], $response['id']);
    }

    public function verifySignature(Request $request): bool
    {
        $secret = config('services.stripe.webhook_secret');
        $header = $request->header('Stripe-Signature');

        if (! $secret || ! $header) {
            return false;
        }

        $parts = collect(explode(',', $header))
            ->mapWithKeys(function ($part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, null);

                return [$key => $value];
            });

        $timestamp = $parts->get('t');
        $signatures = collect(explode(',', $header))
            ->filter(fn ($p) => str_starts_with($p, 'v1='))
            ->map(fn ($p) => substr($p, 3));

        if (! $timestamp || $signatures->isEmpty()) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", $secret);

        return $signatures->contains(fn ($sig) => hash_equals($expected, $sig));
    }

    public function extractSuccessfulReference(Request $request): ?string
    {
        $payload = $request->json()->all();

        if (($payload['type'] ?? null) !== 'checkout.session.completed') {
            return null;
        }

        return $payload['data']['object']['id'] ?? null;
    }
}

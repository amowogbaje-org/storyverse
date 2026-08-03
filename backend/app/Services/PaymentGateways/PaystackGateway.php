<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGateway;
use App\Models\PenName;
use App\Models\Story;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\CheckoutSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaystackGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'paystack';
    }

    public function createCheckoutSession(User $user, SubscriptionPlan $plan): CheckoutSession
    {
        $reference = 'sv_'.Str::uuid();

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email,
                'amount' => (int) round($plan->price * 100), // kobo/cents
                'currency' => $plan->currency,
                'reference' => $reference,
                'callback_url' => config('app.frontend_url').'/subscription?status=success',
                'metadata' => ['user_id' => $user->id, 'plan_id' => $plan->id],
            ])
            ->throw()
            ->json();

        return new CheckoutSession($response['data']['authorization_url'], $reference);
    }

    public function createTipCheckoutSession(User $tipper, PenName $penName, float $amount, string $currency): CheckoutSession
    {
        $reference = 'tip_'.Str::uuid();

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $tipper->email,
                'amount' => (int) round($amount * 100),
                'currency' => $currency,
                'reference' => $reference,
                'callback_url' => config('app.frontend_url')."/authors/{$penName->slug}?tip=success",
                'metadata' => ['tipper_id' => $tipper->id, 'pen_name_id' => $penName->id, 'kind' => 'tip'],
            ])
            ->throw()
            ->json();

        return new CheckoutSession($response['data']['authorization_url'], $reference);
    }

    public function createStoryPurchaseCheckoutSession(User $user, Story $story, float $amount, string $currency): CheckoutSession
    {
        $reference = 'buy_'.Str::uuid();

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email,
                'amount' => (int) round($amount * 100),
                'currency' => $currency,
                'reference' => $reference,
                'callback_url' => config('app.frontend_url')."/stories/{$story->slug}?purchase=success",
                'metadata' => ['user_id' => $user->id, 'story_id' => $story->id, 'kind' => 'story_purchase'],
            ])
            ->throw()
            ->json();

        return new CheckoutSession($response['data']['authorization_url'], $reference);
    }

    public function verifySignature(Request $request): bool
    {
        $secret = config('services.paystack.secret_key');
        $signature = $request->header('x-paystack-signature');

        if (! $secret || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $request->getContent(), $secret), $signature);
    }

    public function extractSuccessfulReference(Request $request): ?string
    {
        $payload = $request->json()->all();

        if (($payload['event'] ?? null) !== 'charge.success') {
            return null;
        }

        return $payload['data']['reference'] ?? null;
    }
}

<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGateway;
use App\Contracts\SupportsPayouts;
use App\Models\PenName;
use App\Models\Payout;
use App\Models\Story;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\CheckoutSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FlutterwaveGateway implements PaymentGateway, SupportsPayouts
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

    public function createTipCheckoutSession(User $tipper, PenName $penName, float $amount, string $currency): CheckoutSession
    {
        $txRef = 'tip_'.Str::uuid();

        $response = Http::withToken(config('services.flutterwave.secret_key'))
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref' => $txRef,
                'amount' => $amount,
                'currency' => $currency,
                'redirect_url' => config('app.frontend_url')."/authors/{$penName->slug}?tip=success",
                'customer' => ['email' => $tipper->email, 'name' => $tipper->name],
                'meta' => ['tipper_id' => $tipper->id, 'pen_name_id' => $penName->id, 'kind' => 'tip'],
            ])
            ->throw()
            ->json();

        return new CheckoutSession($response['data']['link'], $txRef);
    }

    public function createStoryPurchaseCheckoutSession(User $user, Story $story, float $amount, string $currency): CheckoutSession
    {
        $txRef = 'buy_'.Str::uuid();

        $response = Http::withToken(config('services.flutterwave.secret_key'))
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref' => $txRef,
                'amount' => $amount,
                'currency' => $currency,
                'redirect_url' => config('app.frontend_url')."/stories/{$story->slug}?purchase=success",
                'customer' => ['email' => $user->email, 'name' => $user->name],
                'meta' => ['user_id' => $user->id, 'story_id' => $story->id, 'kind' => 'story_purchase'],
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

    /**
     * Only ever called when config('payouts.auto_send_enabled') is true (see
     * GenerateMonthlyPayouts) - real money movement, gated off by default.
     * Requires payout_bank_code to be set (Flutterwave identifies banks by
     * numeric code, not free-text name) - GenerateMonthlyPayouts checks for
     * this and marks the payout 'failed' with a clear reason if it's missing,
     * rather than this method being reached without it.
     */
    public function sendPayout(Payout $payout): string
    {
        $response = Http::withToken(config('services.flutterwave.secret_key'))
            ->post('https://api.flutterwave.com/v3/transfers', [
                'account_bank' => $payout->payout_bank_code,
                'account_number' => $payout->payout_account_number,
                'amount' => $payout->total_amount,
                'currency' => $payout->currency,
                'narration' => 'Storyverse author payout '.$payout->period_start->format('M Y'),
                'reference' => 'payout_'.$payout->id.'_'.Str::random(8),
            ])
            ->throw()
            ->json();

        return (string) $response['data']['id'];
    }
}

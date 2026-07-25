<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\GatewayAvailabilityService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private GatewayAvailabilityService $gateways,
        private \App\Services\PaymentGatewayService $paymentGateway,
        private \App\Services\PaymentGatewayRegistry $gatewayRegistry,
    ) {}

    private const CURRENCY_SYMBOLS = ['USD' => '$', 'GBP' => '£', 'NGN' => '₦'];

    public function plans(Request $request)
    {
        $user = $this->currentUser($request);
        $countryCode = $user?->country_code ?? $request->query('country_code', 'US');

        // A user can set `currency` on their profile independently of country_code
        // (e.g. they travel, or just prefer a different display currency). Honor
        // that explicit choice first - it's what checkout should charge in - and
        // only fall back to the country-derived plan if they haven't set one.
        $plan = null;

        if ($user?->currency) {
            $plan = SubscriptionPlan::where('currency', $user->currency)->where('is_active', true)->first();
        }

        $plan ??= SubscriptionPlan::where('country_code', $countryCode)
            ->where('is_active', true)
            ->first()
            ?? SubscriptionPlan::where('country_code', 'US')->where('is_active', true)->first();

        if (! $plan) {
            return $this->ok([]);
        }

        return $this->ok([[
            'id' => $plan->id,
            'name' => $plan->name,
            'price' => $plan->price,
            'currency_code' => $plan->currency,
            'currency_symbol' => self::CURRENCY_SYMBOLS[$plan->currency] ?? $plan->currency.' ',
            'interval' => $plan->billing_interval,
        ]]);
    }

    public function gateways(Request $request)
    {
        $user = $this->currentUser($request);
        $countryCode = $user?->country_code ?? $request->query('country_code', 'US');

        return $this->ok($this->gateways->availableGateways($countryCode));
    }

    public function checkout(Request $request)
    {
        $user = $this->requireUser($request);

        $data = $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'gateway' => ['required', 'in:'.implode(',', $this->gatewayRegistry->names())],
        ]);

        $plan = SubscriptionPlan::findOrFail($data['plan_id']);

        try {
            $checkout = $this->paymentGateway->initiateCheckout($data['gateway'], $user, $plan);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            report($e);

            return $this->error('gateway_error', 'Could not start checkout with the selected payment provider. Please try again.', 502);
        } catch (\App\Exceptions\UnknownPaymentGatewayException $e) {
            return $this->error('unknown_gateway', $e->getMessage(), 422);
        }

        return $this->ok([
            'checkout_url' => $checkout['checkout_url'],
            'reference' => $checkout['reference'],
            'plan' => $plan,
        ]);
    }

    public function mySubscription(Request $request)
    {
        $user = $this->requireUser($request);

        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->latest('current_period_end')
            ->first();

        return $this->ok($subscription);
    }

    public function cancel(Request $request)
    {
        $user = $this->requireUser($request);

        $subscription = $user->subscriptions()->where('status', 'active')->first();

        if (! $subscription) {
            return $this->error('no_active_subscription', 'No active subscription to cancel.', 404);
        }

        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return $this->ok($subscription);
    }
}

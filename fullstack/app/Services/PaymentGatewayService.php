<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;

/**
 * Orchestrates checkout + subscription activation. Deliberately knows nothing
 * about Stripe, Paystack, or Flutterwave specifically - that's the whole
 * point of depending on PaymentGatewayRegistry (an abstraction) instead of
 * the concrete gateway classes (Dependency Inversion). This class only owns
 * *domain* logic: recording payments and activating/extending subscriptions.
 * Talking to a provider's API is entirely the gateway implementation's job.
 */
class PaymentGatewayService
{
    public function __construct(private PaymentGatewayRegistry $gateways) {}

    /** @return array{checkout_url: string, reference: string} */
    public function initiateCheckout(string $gatewayName, User $user, SubscriptionPlan $plan): array
    {
        $gateway = $this->gateways->get($gatewayName);
        $session = $gateway->createCheckoutSession($user, $plan);

        $this->recordPendingPayment($user, $plan, $gatewayName, $session->reference);

        return ['checkout_url' => $session->checkoutUrl, 'reference' => $session->reference];
    }

    private function recordPendingPayment(User $user, SubscriptionPlan $plan, string $gateway, string $reference): Payment
    {
        return Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => $gateway,
            'gateway_reference' => $reference,
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'status' => 'pending',
        ]);
    }

    /**
     * Called once a webhook confirms a successful charge. Idempotent - safe to
     * call twice for the same reference (gateways routinely redeliver
     * webhooks) - and extends the reader's subscription by one billing period.
     */
    public function activateSubscription(Payment $payment): void
    {
        if ($payment->status === 'success') {
            return; // already processed - webhook redelivery
        }

        $payment->update(['status' => 'success']);

        $plan = $payment->plan;
        if (! $plan) {
            return;
        }

        $periodLength = $plan->billing_interval === 'year' ? '1 year' : '1 month';

        $subscription = Subscription::where('user_id', $payment->user_id)
            ->where('status', 'active')
            ->first();

        if ($subscription) {
            // Renewal: extend from whichever is later, current period end or now,
            // so an early renewal doesn't discard time the reader already paid for.
            $base = $subscription->current_period_end->isFuture() ? $subscription->current_period_end : now();
            $subscription->update([
                'current_period_end' => $base->copy()->add(\DateInterval::createFromDateString($periodLength)),
                'locked_price' => $plan->price,
                'locked_currency' => $plan->currency,
            ]);
        } else {
            $subscription = Subscription::create([
                'user_id' => $payment->user_id,
                'plan_id' => $plan->id,
                'gateway' => $payment->gateway,
                'locked_price' => $plan->price,
                'locked_currency' => $plan->currency,
                'status' => 'active',
                'current_period_end' => now()->add(\DateInterval::createFromDateString($periodLength)),
            ]);
        }

        $payment->update(['subscription_id' => $subscription->id]);
    }
}

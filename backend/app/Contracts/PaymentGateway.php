<?php

namespace App\Contracts;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\CheckoutSession;
use Illuminate\Http\Request;

/**
 * One implementation per payment provider (Stripe, Paystack, Flutterwave, ...).
 * Callers (PaymentGatewayService, WebhookController) only ever depend on this
 * interface, never on a concrete gateway class - that's what lets a new
 * provider be added without touching any existing class (Open/Closed), and
 * lets tests substitute a fake gateway freely (Liskov).
 */
interface PaymentGateway
{
    /** The identifier used in the DB (payments.gateway) and in routes/config. */
    public function name(): string;

    /** Create a hosted checkout session with the provider and return where to send the reader. */
    public function createCheckoutSession(User $user, SubscriptionPlan $plan): CheckoutSession;

    /** Verify the incoming webhook request actually came from this provider. */
    public function verifySignature(Request $request): bool;

    /**
     * If this webhook payload represents a successful payment, return the
     * gateway_reference to look up (matches what createCheckoutSession's
     * CheckoutSession::$reference produced). Return null for any other event
     * type - the caller does nothing with those.
     */
    public function extractSuccessfulReference(Request $request): ?string;
}

<?php

namespace App\Contracts;

use App\Models\PenName;
use App\Models\Story;
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

    /**
     * Create a hosted checkout session for a one-off tip to an author - not
     * tied to a subscription plan, so the amount/currency are whatever the
     * tipper picked rather than coming from a Plan record.
     */
    public function createTipCheckoutSession(User $tipper, PenName $penName, float $amount, string $currency): CheckoutSession;

    /**
     * Create a hosted checkout session for a one-off "buy this book" purchase -
     * a single flat charge that unlocks every episode of $story for $user
     * regardless of subscription status. Amount/currency come from whichever
     * of the story's per-currency prices was resolved for this buyer (see
     * Story::priceFor and StoryPurchaseService), not a Plan record.
     */
    public function createStoryPurchaseCheckoutSession(User $user, Story $story, float $amount, string $currency): CheckoutSession;

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

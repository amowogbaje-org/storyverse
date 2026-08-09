<?php

namespace App\Services;

use App\Models\Story;
use App\Models\StoryPurchase;
use App\Models\User;

class StoryPurchaseService
{
    public function __construct(private PaymentGatewayRegistry $gateways) {}

    /** @return array{checkout_url: string, reference: string} */
    public function initiateCheckout(string $gatewayName, User $user, Story $story): array
    {
        $price = $story->priceFor($user->currency);

        // Guarded even though StoryPurchaseController::checkout() already
        // checks isPurchasable() first - isPurchasable() and priceFor() both
        // read the same prices() relation, so they can't actually disagree,
        // but a null here would otherwise fail confusingly deep inside the
        // payment gateway call rather than with a clear error.
        if (! $price) {
            throw new \RuntimeException("Story #{$story->id} has no price set - checkout should not have been reachable.");
        }

        $gateway = $this->gateways->get($gatewayName);
        $session = $gateway->createStoryPurchaseCheckoutSession(
            $user,
            $story,
            (float) $price->amount,
            $price->currency
        );

        StoryPurchase::create([
            'user_id' => $user->id,
            'story_id' => $story->id,
            'gateway' => $gatewayName,
            'gateway_reference' => $session->reference,
            'amount' => $price->amount,
            'currency' => $price->currency,
            'status' => 'pending',
        ]);

        return ['checkout_url' => $session->checkoutUrl, 'reference' => $session->reference];
    }

    /**
     * Called once a webhook confirms a successful charge. Idempotent - safe to
     * call twice for the same reference (gateways routinely redeliver webhooks).
     */
    public function markSuccessful(StoryPurchase $purchase): void
    {
        if ($purchase->status === 'success') {
            return;
        }

        $purchase->update(['status' => 'success']);
    }

    public function hasPurchased(User $user, Story $story): bool
    {
        return StoryPurchase::where('user_id', $user->id)
            ->where('story_id', $story->id)
            ->where('status', 'success')
            ->exists();
    }
}

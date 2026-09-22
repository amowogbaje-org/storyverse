<?php

namespace App\Services;

use App\Models\PenName;
use App\Models\Tip;
use App\Models\User;
use App\Notifications\TipReceived;

class TipService
{
    public function __construct(private PaymentGatewayRegistry $gateways) {}

    /** @return array{checkout_url: string, reference: string} */
    public function initiateCheckout(string $gatewayName, User $tipper, PenName $penName, float $amount, string $currency, ?string $message): array
    {
        $gateway = $this->gateways->get($gatewayName);
        $session = $gateway->createTipCheckoutSession($tipper, $penName, $amount, $currency);

        Tip::create([
            'pen_name_id' => $penName->id,
            'user_id' => $tipper->id,
            'amount' => $amount,
            'currency' => $currency,
            'gateway' => $gatewayName,
            'gateway_reference' => $session->reference,
            'status' => 'pending',
            'message' => $message,
        ]);

        return ['checkout_url' => $session->checkoutUrl, 'reference' => $session->reference];
    }

    /**
     * Called once a webhook confirms a successful charge. Idempotent - safe to
     * call twice for the same reference (gateways routinely redeliver webhooks).
     */
    public function markSuccessful(Tip $tip): void
    {
        if ($tip->status === 'success') {
            return;
        }

        $tip->update(['status' => 'success']);

        $author = $tip->penName->user;
        if ($author) {
            $author->notify(new TipReceived($tip));
        }
    }
}

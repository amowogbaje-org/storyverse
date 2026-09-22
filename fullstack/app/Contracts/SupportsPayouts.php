<?php

namespace App\Contracts;

use App\Models\Payout;

/**
 * Separate from the core PaymentGateway interface deliberately: collecting
 * payments and sending payouts are different capabilities, and not every
 * gateway a platform might use for collection also handles payouts (or the
 * business might use a totally different provider for outbound transfers).
 * GenerateMonthlyPayouts checks `instanceof` this before attempting an
 * automated transfer, and only when config('payouts.auto_send_enabled') is
 * also true - see that command for the full gating logic.
 */
interface SupportsPayouts
{
    /**
     * Initiates a transfer to the payout's snapshotted bank account details.
     * Returns the gateway's transfer id on success; throws on failure (the
     * caller catches this and marks the payout 'failed' with the reason).
     */
    public function sendPayout(Payout $payout): string;
}

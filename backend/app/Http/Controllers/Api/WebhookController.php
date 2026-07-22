<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentGatewayRegistry;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;

/**
 * One route, one method, for every gateway - each gateway's own signature
 * verification and reference-extraction logic lives on its own class (see
 * app/Services/PaymentGateways/*), reached through PaymentGatewayRegistry.
 * Adding a fourth provider needs zero changes here.
 */
class WebhookController extends Controller
{
    public function __construct(
        private PaymentGatewayRegistry $gateways,
        private PaymentGatewayService $paymentGateway,
    ) {}

    public function handle(Request $request, string $gateway)
    {
        if (! $this->gateways->has($gateway)) {
            return response()->json(['error' => 'unknown gateway'], 404);
        }

        $gatewayImpl = $this->gateways->get($gateway);

        // Verification failures return 400 rather than 200, so a misconfigured
        // secret is visible in the gateway's webhook delivery logs instead of
        // silently swallowed.
        if (! $gatewayImpl->verifySignature($request)) {
            return response()->json(['error' => 'invalid signature'], 400);
        }

        $reference = $gatewayImpl->extractSuccessfulReference($request);

        if ($reference) {
            $payment = Payment::where('gateway', $gateway)->where('gateway_reference', $reference)->first();

            if ($payment) {
                $this->paymentGateway->activateSubscription($payment);
            }
        }

        // Always 200 for a validly-signed webhook we don't act on (e.g. events other
        // than a successful charge) - the gateway would otherwise keep retrying it.
        return response()->json(['received' => true]);
    }
}

<?php

namespace App\Services;

class GatewayAvailabilityService
{
    // NOTE: verify these against each provider's current supported-country docs before launch.
    private array $paystackCountries = ['NG', 'GH', 'ZA', 'KE'];
    private array $stripeCountries = ['US', 'GB', 'CA', 'AU', 'DE', 'FR', 'NL', 'IE', 'SG', 'JP'];

    public function availableGateways(string $countryCode): array
    {
        $gateways = ['flutterwave']; // always available, always primary/default

        // Single-provider phase: Paystack/Stripe are commented out here to match
        // PaymentServiceProvider (which no longer registers them), so the
        // frontend never shows a picker with options that would fail at
        // checkout. Uncomment both blocks together to bring a provider back.
        // if (in_array($countryCode, $this->paystackCountries, true)) {
        //     $gateways[] = 'paystack';
        // }
        //
        // if (in_array($countryCode, $this->stripeCountries, true)) {
        //     $gateways[] = 'stripe';
        // }

        return $gateways;
    }
}

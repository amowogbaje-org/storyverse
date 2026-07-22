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

        if (in_array($countryCode, $this->paystackCountries, true)) {
            $gateways[] = 'paystack';
        }

        if (in_array($countryCode, $this->stripeCountries, true)) {
            $gateways[] = 'stripe';
        }

        return $gateways;
    }
}

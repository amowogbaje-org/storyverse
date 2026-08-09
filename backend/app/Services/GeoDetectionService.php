<?php

namespace App\Services;

use Illuminate\Http\Request;

class GeoDetectionService
{
    private const FALLBACK_COUNTRY = 'US';

    // Direct country -> currency mapping, one entry per currency in
    // config/currencies.php - that config file is the actual source of truth
    // for which currencies are usable at all (story pricing, checkout), this
    // just says which country most naturally maps to each. Previously this
    // went through SubscriptionPlan (whichever plan existed for a country
    // decided its currency); with subscriptions gone as a product, currency
    // detection needed its own home instead of piggybacking on that table.
    private const COUNTRY_CURRENCY = [
        'US' => 'USD',
        'GB' => 'GBP',
        'NG' => 'NGN',
        'PH' => 'PHP',
        'IN' => 'INR',
        'ID' => 'IDR',
        'CA' => 'CAD',
    ];

    public function detect(Request $request): array
    {
        if ($cfCountry = $request->header('CF-IPCountry')) {
            return $this->resolve($cfCountry);
        }

        if ($countryCode = $this->geoIpLookup($request->ip())) {
            return $this->resolve($countryCode);
        }

        if ($locale = $request->input('browser_locale')) {
            if ($countryCode = $this->countryFromLocale($locale)) {
                return $this->resolve($countryCode);
            }
        }

        return $this->resolve(self::FALLBACK_COUNTRY);
    }

    private function resolve(string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);
        $currency = self::COUNTRY_CURRENCY[$countryCode] ?? self::COUNTRY_CURRENCY[self::FALLBACK_COUNTRY];

        // Never hand back a currency this app doesn't actually support for
        // pricing/checkout, even if COUNTRY_CURRENCY above and
        // config/currencies.php ever drift out of sync.
        if (! array_key_exists($currency, config('currencies'))) {
            $currency = 'USD';
        }

        return [
            'country_code' => $countryCode,
            'currency' => $currency,
        ];
    }

    private function geoIpLookup(?string $ip): ?string
    {
        // TODO: wire up MaxMind GeoLite2 local DB lookup here.
        // Returning null falls through to the next detection layer.
        return null;
    }

    private function countryFromLocale(string $locale): ?string
    {
        // e.g. "en-GB" -> "GB"
        if (str_contains($locale, '-')) {
            return strtoupper(explode('-', $locale)[1] ?? '');
        }

        return null;
    }
}

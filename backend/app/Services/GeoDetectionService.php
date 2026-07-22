<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class GeoDetectionService
{
    private const FALLBACK_COUNTRY = 'US';

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
        $plan = SubscriptionPlan::where('country_code', $countryCode)
            ->where('is_active', true)
            ->first();

        $plan ??= SubscriptionPlan::where('country_code', self::FALLBACK_COUNTRY)
            ->where('is_active', true)
            ->first();

        return [
            'country_code' => $countryCode,
            'currency' => $plan?->currency ?? 'USD',
            'matched_plan_id' => $plan?->id,
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

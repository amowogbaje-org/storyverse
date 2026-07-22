<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Regional pricing tuned to purchasing power, not a flat currency conversion.
     * GeoDetectionService falls back to the 'US' row for any unmapped country.
     */
    public function run(): void
    {
        $plans = [
            ['name' => 'Premium Monthly', 'country_code' => 'US', 'currency' => 'USD', 'price' => 10.00, 'billing_interval' => 'month'],
            ['name' => 'Premium Monthly', 'country_code' => 'GB', 'currency' => 'GBP', 'price' => 7.00, 'billing_interval' => 'month'],
            ['name' => 'Premium Monthly', 'country_code' => 'NG', 'currency' => 'NGN', 'price' => 5000.00, 'billing_interval' => 'month'],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['country_code' => $plan['country_code'], 'billing_interval' => $plan['billing_interval']],
                $plan + ['is_active' => true]
            );
        }
    }
}

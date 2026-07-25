<?php

namespace App\Providers;

use App\Services\PaymentGatewayRegistry;
use App\Services\PaymentGateways\FlutterwaveGateway;
// use App\Services\PaymentGateways\PaystackGateway;
// use App\Services\PaymentGateways\StripeGateway;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayRegistry::class, function ($app) {
            return new PaymentGatewayRegistry([
                // Only Flutterwave is live for now (single-provider phase). To bring
                // Stripe/Paystack back: uncomment the imports above, uncomment the
                // lines below, and undo the matching comment-out in
                // GatewayAvailabilityService::availableGateways(). No other code
                // needs to change - see PaymentGatewayRegistry's docblock.
                // $app->make(StripeGateway::class),
                // $app->make(PaystackGateway::class),
                $app->make(FlutterwaveGateway::class),
            ]);
        });
    }
}

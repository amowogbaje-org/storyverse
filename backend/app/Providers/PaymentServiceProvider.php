<?php

namespace App\Providers;

use App\Services\PaymentGatewayRegistry;
use App\Services\PaymentGateways\FlutterwaveGateway;
use App\Services\PaymentGateways\PaystackGateway;
use App\Services\PaymentGateways\StripeGateway;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayRegistry::class, function ($app) {
            return new PaymentGatewayRegistry([
                $app->make(StripeGateway::class),
                $app->make(PaystackGateway::class),
                $app->make(FlutterwaveGateway::class),
            ]);
        });
    }
}

<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Exceptions\UnknownPaymentGatewayException;
use Illuminate\Support\Collection;

/**
 * Adding a new provider means writing one class implementing PaymentGateway
 * and adding one line in PaymentServiceProvider - nothing here, in
 * PaymentGatewayService, or in WebhookController needs to change.
 */
class PaymentGatewayRegistry
{
    /** @var Collection<string, PaymentGateway> */
    private Collection $gateways;

    /** @param PaymentGateway[] $gateways */
    public function __construct(array $gateways)
    {
        $this->gateways = collect($gateways)->keyBy(fn (PaymentGateway $g) => $g->name());
    }

    public function get(string $name): PaymentGateway
    {
        return $this->gateways->get($name) ?? throw new UnknownPaymentGatewayException($name);
    }

    public function has(string $name): bool
    {
        return $this->gateways->has($name);
    }

    /** @return string[] */
    public function names(): array
    {
        return $this->gateways->keys()->all();
    }
}

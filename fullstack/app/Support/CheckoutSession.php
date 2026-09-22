<?php

namespace App\Support;

/** Immutable result of starting a checkout with a payment gateway. */
final class CheckoutSession
{
    public function __construct(
        public readonly string $checkoutUrl,
        public readonly string $reference,
    ) {}
}

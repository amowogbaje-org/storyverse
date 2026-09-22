<?php

namespace App\Exceptions;

use InvalidArgumentException;

class UnknownPaymentGatewayException extends InvalidArgumentException
{
    public function __construct(string $gatewayName)
    {
        parent::__construct("Unknown payment gateway: {$gatewayName}");
    }
}

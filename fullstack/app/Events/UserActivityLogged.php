<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class UserActivityLogged
{
    use Dispatchable;

    public function __construct(
        public int $userId,
        public string $eventType,
        public array $metadata = [],
    ) {}
}

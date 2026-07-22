<?php

return [
    'secret' => env('JWT_SECRET'),
    'ttl_minutes' => env('JWT_TTL', 60),
];

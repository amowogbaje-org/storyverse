<?php

namespace Tests;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Auth header for a given user — this app uses hand-rolled JWT, not Sanctum. */
    protected function bearerFor(User $user): array
    {
        $token = app(JwtService::class)->issue($user);

        return ['Authorization' => "Bearer {$token}"];
    }
}

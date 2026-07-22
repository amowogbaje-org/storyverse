<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    public function issue(User $user): string
    {
        $payload = [
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + (60 * (int) config('jwt.ttl_minutes', 60)),
        ];

        return JWT::encode($payload, config('jwt.secret'), 'HS256');
    }

    public function decode(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function userFromToken(string $token): ?User
    {
        $payload = $this->decode($token);

        if (! $payload || ! isset($payload->sub)) {
            return null;
        }

        return User::find($payload->sub);
    }
}

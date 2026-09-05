<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Long-lived, DB-backed, revocable companion to JwtService's short-lived
 * access token. The access token alone can no longer keep a reader signed
 * in for weeks - it's meant to expire quickly - so this is what actually
 * carries a login across days/weeks: the client trades a still-valid
 * refresh token for a fresh access token via /auth/refresh whenever the
 * access token has expired, without asking the reader to sign in again.
 */
class RefreshTokenService
{
    /** Issues a brand new refresh token for $user and returns the plaintext. */
    public function issue(User $user): array
    {
        $plain = Str::random(64);

        $token = RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => $this->hash($plain),
            'expires_at' => now()->addDays((int) config('jwt.refresh_ttl_days', 30)),
            'created_at' => now(),
        ]);

        return ['token' => $plain, 'expires_at' => $token->expires_at];
    }

    /** Looks up the still-valid (unexpired, unrevoked) row behind a plaintext token, if any. */
    public function find(string $plainToken): ?RefreshToken
    {
        return RefreshToken::active()->where('token_hash', $this->hash($plainToken))->first();
    }

    /**
     * Redeems $plainToken for a fresh access+refresh pair, rotating the
     * refresh token in the process: the old one is revoked so it can never
     * be redeemed twice. Single-use tokens mean a copy of an old, already-
     * used refresh token (e.g. sniffed from a log) is worthless - it fails
     * the "still active" check as soon as the real client rotates past it.
     * Returns null if $plainToken isn't currently valid.
     */
    public function rotate(string $plainToken): ?array
    {
        $stored = $this->find($plainToken);

        if (! $stored) {
            return null;
        }

        $user = $stored->user;
        $stored->update(['revoked_at' => now()]);

        return ['user' => $user, ...$this->issue($user)];
    }

    public function revoke(string $plainToken): void
    {
        RefreshToken::where('token_hash', $this->hash($plainToken))->update(['revoked_at' => now()]);
    }

    /** Signs the reader out everywhere - e.g. on password change. */
    public function revokeAllForUser(User $user): void
    {
        RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    private function hash(string $plainToken): string
    {
        // SHA-256, not bcrypt: this is a high-entropy 64-char random token
        // (nothing to brute-force offline, unlike a human password), and a
        // fast, deterministic hash is what lets a lookup query find it by
        // hash at all - bcrypt is intentionally slow and salted per-call,
        // which a WHERE token_hash = ? lookup can't use anyway.
        return hash('sha256', $plainToken);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    private const CLIENT_ID = 'test-client-id.apps.googleusercontent.com';
    private const KID = 'test-kid-1';

    private string $privateKeyPem;
    private array $jwks;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => self::CLIENT_ID]);
        Cache::forget('google_jwks');

        // A throwaway keypair generated fresh per test run - this never
        // needs to be a real Google key, just a real RSA keypair so a real
        // RS256-signed JWT can be verified against its own public half,
        // exactly as verifyGoogleIdToken() would verify a real Google token
        // against Google's real published keys.
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $this->privateKeyPem);
        $details = openssl_pkey_get_details($resource);

        $this->jwks = ['keys' => [[
            'kty' => 'RSA',
            'kid' => self::KID,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ]]];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function fakeGoogleToken(array $claimOverrides = []): string
    {
        Http::fake([
            'www.googleapis.com/oauth2/v3/certs' => Http::response($this->jwks, 200),
        ]);

        $claims = array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENT_ID,
            'sub' => '1234567890',
            'email' => 'reader@example.com',
            'email_verified' => true,
            'name' => 'Ada Reader',
            'iat' => time(),
            'exp' => time() + 3600,
        ], $claimOverrides);

        return JWT::encode($claims, $this->privateKeyPem, 'RS256', self::KID);
    }

    public function test_a_valid_google_token_signs_the_user_in(): void
    {
        $credential = $this->fakeGoogleToken();

        $response = $this->postJson('/api/v1/auth/google', ['credential' => $credential]);

        $response->assertStatus(200)->assertJsonPath('data.user.email', 'reader@example.com');
        $this->assertDatabaseHas('users', ['email' => 'reader@example.com', 'google_id' => '1234567890']);
    }

    public function test_a_token_for_a_different_audience_is_rejected(): void
    {
        $credential = $this->fakeGoogleToken(['aud' => 'someone-elses-client-id.apps.googleusercontent.com']);

        $this->postJson('/api/v1/auth/google', ['credential' => $credential])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_google_token');
    }

    public function test_an_unverified_email_is_rejected(): void
    {
        $credential = $this->fakeGoogleToken(['email_verified' => false]);

        $this->postJson('/api/v1/auth/google', ['credential' => $credential])->assertStatus(422);
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $credential = $this->fakeGoogleToken(['iat' => time() - 7200, 'exp' => time() - 3600]);

        $this->postJson('/api/v1/auth/google', ['credential' => $credential])->assertStatus(422);
    }

    public function test_a_token_signed_by_an_unknown_key_is_rejected(): void
    {
        $otherResource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($otherResource, $otherPrivateKeyPem);

        Http::fake(['www.googleapis.com/oauth2/v3/certs' => Http::response($this->jwks, 200)]);

        $credential = JWT::encode([
            'iss' => 'https://accounts.google.com', 'aud' => self::CLIENT_ID, 'sub' => '999',
            'email' => 'imposter@example.com', 'email_verified' => true,
            'iat' => time(), 'exp' => time() + 3600,
        ], $otherPrivateKeyPem, 'RS256', self::KID);

        $this->postJson('/api/v1/auth/google', ['credential' => $credential])->assertStatus(422);
    }

    public function test_a_second_login_does_not_refetch_googles_keys(): void
    {
        $first = $this->fakeGoogleToken();
        $this->postJson('/api/v1/auth/google', ['credential' => $first])->assertStatus(200);

        // Deliberately do NOT call fakeGoogleToken() again here - it would
        // re-fake a valid certs response and hide the very thing this test
        // checks. Instead, fake the certs endpoint to return an EMPTY keyset:
        // if the second login refetches keys instead of using the 1-hour
        // cache (see verifyGoogleIdToken), verification would fail against
        // an empty keyset and this assertion would catch it.
        Http::fake(['www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => []], 200)]);

        $credential = JWT::encode([
            'iss' => 'https://accounts.google.com', 'aud' => self::CLIENT_ID, 'sub' => '1234567890',
            'email' => 'reader@example.com', 'email_verified' => true, 'iat' => time(), 'exp' => time() + 3600,
        ], $this->privateKeyPem, 'RS256', self::KID);

        $this->postJson('/api/v1/auth/google', ['credential' => $credential])->assertStatus(200);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\OtpCodeNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private function validRegisterPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => true,
            'country_code' => 'GB',
        ], $overrides);
    }

    public function test_register_creates_an_unverified_user_and_sends_an_otp_instead_of_a_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validRegisterPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.user.name', 'Ada Lovelace')
            ->assertJsonPath('data.user.display_name', 'Ada Lovelace')
            ->assertJsonPath('data.user.has_premium_access', false)
            ->assertJsonPath('data.user.country_code', 'GB')
            ->assertJsonPath('data.requires_verification', true)
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com', 'email_verified_at' => null]);
        Notification::assertSentOnDemand(OtpCodeNotification::class);
    }

    public function test_register_rejects_a_mismatched_password_confirmation(): void
    {
        $this->postJson('/api/v1/auth/register', $this->validRegisterPayload([
            'password_confirmation' => 'something-else',
        ]))->assertStatus(422);
    }

    public function test_register_rejects_without_accepting_terms(): void
    {
        $this->postJson('/api/v1/auth/register', $this->validRegisterPayload([
            'accept_terms' => false,
        ]))->assertStatus(422);
    }

    public function test_register_logs_a_user_registered_activity_event(): void
    {
        $this->postJson('/api/v1/auth/register', $this->validRegisterPayload([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]))->assertStatus(201);

        $user = User::where('email', 'grace@example.com')->firstOrFail();

        $this->assertDatabaseHas('user_activity_events', [
            'user_id' => $user->id,
            'event_type' => 'user_registered',
        ]);
    }

    public function test_register_rejects_a_duplicate_email(): void
    {
        User::create(['name' => 'Existing', 'email' => 'dupe@example.com', 'password' => bcrypt('x'), 'role' => 'reader']);

        $this->postJson('/api/v1/auth/register', $this->validRegisterPayload([
            'name' => 'New Person',
            'email' => 'dupe@example.com',
        ]))->assertStatus(422);
    }

    public function test_full_signup_flow_verifies_via_otp_and_returns_a_token(): void
    {
        $this->postJson('/api/v1/auth/register', $this->validRegisterPayload([
            'email' => 'verify-me@example.com',
        ]))->assertStatus(201);

        $this->assertNotNull(Cache::get('otp:verify-me@example.com'));

        // Wrong code is rejected without consuming the real one.
        $this->postJson('/api/v1/auth/otp/verify', [
            'email' => 'verify-me@example.com',
            'code' => '000000',
        ])->assertStatus(422);

        // The real code was mailed via OtpCodeNotification (not asserted here); seed a
        // known one directly so the rest of this test can exercise the verify step.
        Cache::put('otp:verify-me@example.com', Hash::make('654321'), now()->addMinutes(10));

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'email' => 'verify-me@example.com',
            'code' => '654321',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['data' => ['user', 'token']]);

        $user = User::where('email', 'verify-me@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_login_succeeds_with_correct_credentials(): void
    {
        User::create([
            'name' => 'Login Test',
            'email' => 'login@example.com',
            'password' => bcrypt('password123'),
            'role' => 'reader',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ])->assertStatus(200)->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::create([
            'name' => 'Login Test',
            'email' => 'login2@example.com',
            'password' => bcrypt('password123'),
            'role' => 'reader',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'login2@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::create(['name' => 'Me Test', 'email' => 'me@example.com', 'password' => bcrypt('x'), 'role' => 'reader']);

        $this->getJson('/api/v1/me', $this->bearerFor($user))
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'me@example.com');
    }

    public function test_a_reader_can_request_to_become_an_author_but_role_does_not_change_yet(): void
    {
        $user = User::create(['name' => 'Future Author', 'email' => 'future@example.com', 'password' => bcrypt('x'), 'role' => 'reader']);

        $this->postJson('/api/v1/me/request-author', [], $this->bearerFor($user))
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'reader')
            ->assertJsonPath('data.author_request_status', 'pending');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'reader', 'author_request_status' => 'pending']);
    }

    public function test_requesting_author_access_is_a_no_op_for_admins(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x'), 'role' => 'admin']);

        $this->postJson('/api/v1/me/request-author', [], $this->bearerFor($admin))
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.author_request_status', 'none');
    }
}

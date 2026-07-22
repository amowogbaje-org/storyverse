<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_register_creates_a_user_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'country_code' => 'GB',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.name', 'Ada Lovelace')
            ->assertJsonPath('data.user.display_name', 'Ada Lovelace')
            ->assertJsonPath('data.user.has_active_premium_subscription', false)
            ->assertJsonPath('data.user.country_code', 'GB')
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }

    public function test_register_logs_a_user_registered_activity_event(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'password' => 'password123',
        ])->assertStatus(201);

        $user = User::where('email', 'grace@example.com')->firstOrFail();

        $this->assertDatabaseHas('user_activity_events', [
            'user_id' => $user->id,
            'event_type' => 'user_registered',
        ]);
    }

    public function test_register_rejects_a_duplicate_email(): void
    {
        User::create(['name' => 'Existing', 'email' => 'dupe@example.com', 'password' => bcrypt('x'), 'role' => 'reader']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'New Person',
            'email' => 'dupe@example.com',
            'password' => 'password123',
        ])->assertStatus(422);
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

    public function test_a_reader_can_become_an_author(): void
    {
        $user = User::create(['name' => 'Future Author', 'email' => 'future@example.com', 'password' => bcrypt('x'), 'role' => 'reader']);

        $this->postJson('/api/v1/me/become-author', [], $this->bearerFor($user))
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'author');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'author']);
    }

    public function test_becoming_an_author_is_idempotent_for_admins(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x'), 'role' => 'admin']);

        $this->postJson('/api/v1/me/become-author', [], $this->bearerFor($admin))
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'admin');
    }
}

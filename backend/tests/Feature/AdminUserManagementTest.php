<?php

namespace Tests\Feature;

use App\Models\PenName;
use App\Models\Story;
use App\Models\User;
use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use CreatesStoryFixtures;

    public function test_reader_cannot_reach_the_user_management_api(): void
    {
        $reader = $this->createReader();

        $this->getJson('/api/v1/admin/users', $this->bearerFor($reader))->assertStatus(403);
    }

    public function test_author_cannot_reach_the_user_management_api(): void
    {
        $author = $this->createAuthor();

        $this->getJson('/api/v1/admin/users', $this->bearerFor($author))->assertStatus(403);
    }

    public function test_requesting_author_access_puts_a_reader_in_the_pending_queue(): void
    {
        $admin = $this->createAdmin();
        $reader = $this->createReader();

        $this->postJson('/api/v1/me/request-author', [], $this->bearerFor($reader))->assertStatus(200);

        $this->getJson('/api/v1/admin/users?status=pending', $this->bearerFor($admin))
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $reader->id, 'author_request_status' => 'pending']);
    }

    public function test_admin_can_grant_author_access_to_a_pending_request(): void
    {
        $admin = $this->createAdmin();
        $reader = $this->createReader(['author_request_status' => 'pending', 'author_requested_at' => now()]);

        $this->postJson("/api/v1/admin/users/{$reader->id}/grant-author", [], $this->bearerFor($admin))
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'author')
            ->assertJsonPath('data.author_request_status', 'none');

        $this->assertDatabaseHas('users', ['id' => $reader->id, 'role' => 'author', 'author_request_status' => 'none']);
    }

    public function test_admin_can_grant_author_access_directly_without_a_request(): void
    {
        $admin = $this->createAdmin();
        $reader = $this->createReader();

        $this->postJson("/api/v1/admin/users/{$reader->id}/grant-author", [], $this->bearerFor($admin))
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'author');
    }

    public function test_admin_can_reject_a_pending_request_without_changing_role(): void
    {
        $admin = $this->createAdmin();
        $reader = $this->createReader(['author_request_status' => 'pending', 'author_requested_at' => now()]);

        $this->postJson("/api/v1/admin/users/{$reader->id}/reject-author-request", [], $this->bearerFor($admin))
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'reader')
            ->assertJsonPath('data.author_request_status', 'rejected');

        $this->assertDatabaseHas('users', ['id' => $reader->id, 'role' => 'reader', 'author_request_status' => 'rejected']);
    }

    public function test_admin_can_revoke_author_access_and_it_unpublishes_their_stories_by_default(): void
    {
        $admin = $this->createAdmin();
        $author = $this->createAuthor();
        ['story' => $story] = $this->createStoryWithEpisodes(['author_id' => $author->id, 'status' => 'published']);

        $this->postJson("/api/v1/admin/users/{$author->id}/revoke-author", [], $this->bearerFor($admin))
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'reader')
            ->assertJsonPath('data.unpublished_stories_count', 1);

        $this->assertDatabaseHas('users', ['id' => $author->id, 'role' => 'reader']);
        $this->assertDatabaseHas('stories', ['id' => $story->id, 'status' => 'draft']);
    }

    public function test_admin_can_revoke_author_access_without_touching_their_stories(): void
    {
        $admin = $this->createAdmin();
        $author = $this->createAuthor();
        ['story' => $story] = $this->createStoryWithEpisodes(['author_id' => $author->id, 'status' => 'published']);

        $this->postJson(
            "/api/v1/admin/users/{$author->id}/revoke-author",
            ['unpublish_stories' => false],
            $this->bearerFor($admin)
        )->assertStatus(200)->assertJsonPath('data.unpublished_stories_count', 0);

        $this->assertDatabaseHas('stories', ['id' => $story->id, 'status' => 'published']);
    }

    public function test_revoking_a_non_author_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $reader = $this->createReader();

        $this->postJson("/api/v1/admin/users/{$reader->id}/revoke-author", [], $this->bearerFor($admin))
            ->assertStatus(422);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PenName;
use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class AdminContentManagementTest extends TestCase
{
    use CreatesStoryFixtures;

    public function test_reader_cannot_reach_the_content_management_api(): void
    {
        $reader = $this->createReader();

        $this->getJson('/api/v1/admin/stories', $this->bearerFor($reader))->assertStatus(403);
    }

    public function test_author_can_create_a_pen_name_and_it_becomes_default_when_first(): void
    {
        $author = $this->createAuthor();

        $response = $this->postJson('/api/v1/admin/pen-names', [
            'display_name' => 'Nightshade Ink',
        ], $this->bearerFor($author));

        $response->assertStatus(201)->assertJsonPath('data.is_default', true);
        $this->assertDatabaseHas('pen_names', ['user_id' => $author->id, 'display_name' => 'Nightshade Ink']);
    }

    public function test_author_can_create_a_story_under_their_own_pen_name(): void
    {
        $author = $this->createAuthor();
        $penName = PenName::create([
            'user_id' => $author->id, 'display_name' => 'Author Pen', 'slug' => 'author-pen', 'is_default' => true,
        ]);
        $category = Category::create(['name' => 'Romance', 'slug' => 'romance']);

        $response = $this->postJson('/api/v1/admin/stories', [
            'pen_name_id' => $penName->id,
            'category_id' => $category->id,
            'title' => 'My New Story',
            'description' => 'A description.',
            'cover_image_url' => 'https://example.com/c.jpg',
            'access_type' => 'free',
        ], $this->bearerFor($author));

        $response->assertStatus(201)->assertJsonPath('data.status', 'draft');
    }

    public function test_author_cannot_create_a_story_under_someone_elses_pen_name(): void
    {
        $author = $this->createAuthor();
        $otherAuthor = $this->createAuthor();
        $otherPenName = PenName::create([
            'user_id' => $otherAuthor->id, 'display_name' => 'Other Pen', 'slug' => 'other-pen', 'is_default' => true,
        ]);
        $category = Category::create(['name' => 'Romance', 'slug' => 'romance']);

        $this->postJson('/api/v1/admin/stories', [
            'pen_name_id' => $otherPenName->id,
            'category_id' => $category->id,
            'title' => 'Not Mine',
            'description' => 'x',
            'cover_image_url' => 'https://example.com/c.jpg',
            'access_type' => 'free',
        ], $this->bearerFor($author))->assertStatus(403);
    }

    public function test_publishing_a_story_requires_at_least_one_published_episode(): void
    {
        $author = $this->createAuthor();
        ['story' => $story] = $this->createStoryWithEpisodes(['author_id' => $author->id, 'status' => 'draft'], episodeCount: 0);

        $this->postJson("/api/v1/admin/stories/{$story->id}/publish", [], $this->bearerFor($author))
            ->assertStatus(422);
    }

    public function test_author_can_add_and_publish_an_episode_then_publish_the_story(): void
    {
        $author = $this->createAuthor();
        ['story' => $story] = $this->createStoryWithEpisodes(['author_id' => $author->id, 'status' => 'draft'], episodeCount: 0);
        $headers = $this->bearerFor($author);

        $episode = $this->postJson("/api/v1/admin/stories/{$story->id}/episodes", [
            'title' => 'Episode 1',
            'content' => 'Once upon a time.',
        ], $headers)->assertStatus(201)->json('data');

        $this->postJson("/api/v1/admin/stories/{$story->id}/episodes/{$episode['id']}/publish", [], $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        $this->postJson("/api/v1/admin/stories/{$story->id}/publish", [], $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'published');
    }

    public function test_author_cannot_edit_another_authors_story(): void
    {
        $owner = $this->createAuthor();
        $intruder = $this->createAuthor();
        ['story' => $story] = $this->createStoryWithEpisodes(['author_id' => $owner->id]);

        $this->patchJson("/api/v1/admin/stories/{$story->id}", ['title' => 'Hijacked'], $this->bearerFor($intruder))
            ->assertStatus(403);
    }

    public function test_admin_can_edit_any_authors_story(): void
    {
        $owner = $this->createAuthor();
        $admin = $this->createAdmin();
        ['story' => $story] = $this->createStoryWithEpisodes(['author_id' => $owner->id]);

        $this->patchJson("/api/v1/admin/stories/{$story->id}", ['title' => 'Edited by admin'], $this->bearerFor($admin))
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Edited by admin');
    }

    public function test_author_dashboard_only_reflects_their_own_stories(): void
    {
        $author = $this->createAuthor();
        $otherAuthor = $this->createAuthor();
        $this->createStoryWithEpisodes(['author_id' => $author->id]);
        $this->createStoryWithEpisodes(['author_id' => $otherAuthor->id]);

        $response = $this->getJson('/api/v1/admin/dashboard', $this->bearerFor($author));

        $response->assertStatus(200)->assertJsonPath('data.stories_count', 1);
    }
}

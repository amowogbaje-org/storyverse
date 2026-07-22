<?php

namespace Tests\Feature;

use App\Services\PlatformMetricsService;
use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class InteractionTest extends TestCase
{
    use CreatesStoryFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        // Interactions don't care about the monetization state, but story detail /
        // episode routes do — keep it in bootstrap phase so fixtures stay simple.
        $this->mock(PlatformMetricsService::class, function ($mock) {
            $mock->shouldReceive('isMonetizationEnabled')->andReturn(false);
        });
    }

    public function test_liking_a_story_requires_authentication(): void
    {
        ['story' => $story] = $this->createStoryWithEpisodes();

        $this->postJson("/api/v1/stories/{$story->slug}/like")->assertStatus(401);
    }

    public function test_a_reader_can_like_and_unlike_a_story(): void
    {
        ['story' => $story] = $this->createStoryWithEpisodes();
        $reader = $this->createReader();
        $headers = $this->bearerFor($reader);

        $this->postJson("/api/v1/stories/{$story->slug}/like", [], $headers)->assertStatus(200);
        $this->assertDatabaseHas('story_likes', ['user_id' => $reader->id, 'story_id' => $story->id]);

        $this->getJson("/api/v1/stories/{$story->slug}", $headers)
            ->assertJsonPath('data.is_liked_by_user', true)
            ->assertJsonPath('data.likes_count', 1);

        $this->deleteJson("/api/v1/stories/{$story->slug}/like", [], $headers)->assertStatus(200);
        $this->assertDatabaseMissing('story_likes', ['user_id' => $reader->id, 'story_id' => $story->id]);
    }

    public function test_a_reader_can_bookmark_a_story(): void
    {
        ['story' => $story] = $this->createStoryWithEpisodes();
        $reader = $this->createReader();
        $headers = $this->bearerFor($reader);

        $this->postJson("/api/v1/stories/{$story->slug}/bookmark", [], $headers)->assertStatus(200);

        $this->getJson('/api/v1/me/bookmarks', $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.0.slug', $story->slug);
    }

    /**
     * Regression test: comments used to come back with a nested `user` object while
     * the frontend read `comment.user_display_name` — this asserts the fixed shape.
     */
    public function test_posting_a_comment_returns_a_flat_user_display_name(): void
    {
        ['story' => $story] = $this->createStoryWithEpisodes();
        $reader = $this->createReader(['name' => 'Comment Author']);

        $response = $this->postJson("/api/v1/stories/{$story->slug}/comments", [
            'body' => 'Loved this episode!',
        ], $this->bearerFor($reader));

        $response->assertStatus(201)
            ->assertJsonPath('data.user_display_name', 'Comment Author')
            ->assertJsonPath('data.body', 'Loved this episode!');

        $this->getJson("/api/v1/stories/{$story->slug}/comments")
            ->assertStatus(200)
            ->assertJsonPath('data.0.user_display_name', 'Comment Author');
    }

    public function test_a_reader_cannot_delete_someone_elses_comment(): void
    {
        ['story' => $story] = $this->createStoryWithEpisodes();
        $author = $this->createReader();
        $intruder = $this->createReader();

        $comment = $story->comments()->create(['user_id' => $author->id, 'body' => 'Mine.']);

        $this->deleteJson("/api/v1/comments/{$comment->id}", [], $this->bearerFor($intruder))
            ->assertStatus(403);
    }
}

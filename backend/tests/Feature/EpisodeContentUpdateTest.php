<?php

namespace Tests\Feature;

use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class EpisodeContentUpdateTest extends TestCase
{
    use CreatesStoryFixtures;

    private function updateEpisode($author, $story, $episode, array $payload)
    {
        return $this->patchJson(
            "/api/v1/admin/stories/{$story->id}/episodes/{$episode->id}",
            $payload,
            $this->bearerFor($author)
        );
    }

    public function test_editing_raw_content_alone_mirrors_it_into_content_and_queues_restyling(): void
    {
        $author = $this->createAuthor();
        ['story' => $story, 'episodes' => $episodes] = $this->createStoryWithEpisodes(['author_id' => $author->id], episodeCount: 1);
        $episode = $episodes->first();
        $episode->update(['content' => 'Old *styled* text.', 'styled_at' => now()]);

        $response = $this->updateEpisode($author, $story, $episode, ['raw_content' => 'Brand new raw text.']);

        $response->assertStatus(200)
            ->assertJsonPath('data.raw_content', 'Brand new raw text.')
            ->assertJsonPath('data.content', 'Brand new raw text.')
            ->assertJsonPath('data.styled_at', null);
    }

    public function test_editing_content_alone_is_a_manual_style_and_does_not_touch_raw_content(): void
    {
        $author = $this->createAuthor();
        ['story' => $story, 'episodes' => $episodes] = $this->createStoryWithEpisodes(['author_id' => $author->id], episodeCount: 1);
        $episode = $episodes->first();
        $episode->update(['raw_content' => 'The original words.', 'content' => 'The original words.', 'styled_at' => null]);

        $response = $this->updateEpisode($author, $story, $episode, ['content' => 'The *original* words.']);

        $response->assertStatus(200)
            ->assertJsonPath('data.content', 'The *original* words.')
            ->assertJsonPath('data.raw_content', 'The original words.') // unchanged
            ->assertJsonPath('data.styling_attempts', 0);
        $this->assertNotNull($response->json('data.styled_at'));
    }

    public function test_editing_both_at_once_the_manual_style_wins_over_the_mirror(): void
    {
        $author = $this->createAuthor();
        ['story' => $story, 'episodes' => $episodes] = $this->createStoryWithEpisodes(['author_id' => $author->id], episodeCount: 1);
        $episode = $episodes->first();

        $response = $this->updateEpisode($author, $story, $episode, [
            'raw_content' => 'Fresh raw text.',
            'content' => 'Fresh *raw* text, styled by hand.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.raw_content', 'Fresh raw text.')
            ->assertJsonPath('data.content', 'Fresh *raw* text, styled by hand.');
        $this->assertNotNull($response->json('data.styled_at'));
    }

    public function test_a_title_only_edit_touches_neither_content_field(): void
    {
        $author = $this->createAuthor();
        ['story' => $story, 'episodes' => $episodes] = $this->createStoryWithEpisodes(['author_id' => $author->id], episodeCount: 1);
        $episode = $episodes->first();
        $episode->update(['raw_content' => 'Untouched.', 'content' => 'Untouched, *styled*.', 'styled_at' => now()]);
        $styledAtBefore = $episode->fresh()->styled_at;

        $response = $this->updateEpisode($author, $story, $episode, ['title' => 'A Better Title']);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'A Better Title')
            ->assertJsonPath('data.raw_content', 'Untouched.')
            ->assertJsonPath('data.content', 'Untouched, *styled*.');
        $this->assertTrue($styledAtBefore->equalTo($response->json('data.styled_at')));
    }
}

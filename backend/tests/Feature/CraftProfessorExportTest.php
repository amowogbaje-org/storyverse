<?php

namespace Tests\Feature;

use App\Models\Episode;
use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class CraftProfessorExportTest extends TestCase
{
    use CreatesStoryFixtures;

    public function test_exports_raw_content_not_the_styled_content(): void
    {
        ['story' => $story, 'episodes' => $episodes] = $this->createStoryWithEpisodes(episodeCount: 1);
        $episode = $episodes->first();
        $episode->update([
            'content' => 'This is the **styled** version.',
            'raw_content' => 'This is the styled version.',
            'raw_content_updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/stories/{$story->slug}/json");

        $response->assertStatus(200);
        $this->assertSame('This is the styled version.', $response->json('episodes.0.content'));
    }

    public function test_falls_back_to_content_for_an_episode_saved_before_raw_content_existed(): void
    {
        ['story' => $story, 'episodes' => $episodes] = $this->createStoryWithEpisodes(episodeCount: 1);
        $episode = $episodes->first();
        // Simulates a pre-migration row: content set, raw_content/raw_content_updated_at never populated.
        $episode->update(['content' => 'Legacy unstyled content.', 'raw_content' => null, 'raw_content_updated_at' => null]);

        $response = $this->getJson("/api/v1/stories/{$story->slug}/json");

        $this->assertSame('Legacy unstyled content.', $response->json('episodes.0.content'));
    }

    public function test_the_styling_agent_overwriting_content_does_not_by_itself_trigger_a_resend(): void
    {
        ['story' => $story, 'episodes' => $episodes] = $this->createStoryWithEpisodes(episodeCount: 1);
        $episode = $episodes->first();
        $episode->update([
            'raw_content' => 'Original text.',
            'raw_content_updated_at' => now()->subHour(),
        ]);
        // content_synced_at isn't mass-assignable on the model (by design - it's
        // only ever set by CraftProfessorExportController itself), so it's set
        // via the query builder here too, same as production code does.
        Episode::where('id', $episode->id)->update(['content_synced_at' => now()]); // already sent once

        // Simulate StyleEpisodes doing its job: it only ever writes content/styled_at,
        // never raw_content_updated_at (see StyleEpisodes::styleOne).
        Episode::where('id', $episode->id)->update(['content' => 'Original text, *styled*.', 'styled_at' => now()]);

        $response = $this->getJson("/api/v1/stories/{$story->slug}/json");

        // Not re-sent (still null) because raw_content_updated_at is older than
        // content_synced_at - only an author edit should trigger a resend.
        $this->assertNull($response->json('episodes.0.content'));
    }

    public function test_an_author_edit_after_the_last_sync_does_trigger_a_resend(): void
    {
        ['story' => $story, 'episodes' => $episodes] = $this->createStoryWithEpisodes(episodeCount: 1);
        $episode = $episodes->first();
        $episode->update([
            'raw_content' => 'First draft.',
            'raw_content_updated_at' => now()->subDay(),
        ]);
        Episode::where('id', $episode->id)->update(['content_synced_at' => now()->subHours(12)]);

        // A later author edit (EpisodeManagementController::update always bumps this together with raw_content).
        $episode->update(['raw_content' => 'Revised draft.', 'raw_content_updated_at' => now()]);

        $response = $this->getJson("/api/v1/stories/{$story->slug}/json");

        $this->assertSame('Revised draft.', $response->json('episodes.0.content'));
    }
}

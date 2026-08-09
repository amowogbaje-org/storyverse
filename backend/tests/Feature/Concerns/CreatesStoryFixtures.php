<?php

namespace Tests\Feature\Concerns;

use App\Models\Category;
use App\Models\Episode;
use App\Models\PenName;
use App\Models\Story;
use App\Models\User;
use App\Services\PlatformMetricsService;
use Illuminate\Support\Str;

/**
 * Hand-rolled fixtures instead of model factories — the models in this
 * skeleton don't use HasFactory yet, and these tests intentionally avoid
 * touching model files just to enable factories.
 */
trait CreatesStoryFixtures
{
    /**
     * Mocks PlatformMetricsService so a test doesn't need real read-count
     * data to cross the platform-wide monetization threshold - see
     * StoryAccessService::accessibleEpisodeLimit(). $hasPriorAccess controls
     * the grandfathering check (a reader who already has reading history on
     * a story before monetization turned on keeps full access).
     */
    protected function monetizationEnabled(bool $hasPriorAccess = false): void
    {
        $this->mock(PlatformMetricsService::class, function ($mock) use ($hasPriorAccess) {
            $mock->shouldReceive('isMonetizationEnabled')->andReturn(true);
            $mock->shouldReceive('hasPriorAccess')->andReturn($hasPriorAccess);
        });
    }

    protected function createAuthor(): User
    {
        return User::create([
            'name' => 'Test Author',
            'email' => 'author'.Str::random(8).'@example.com',
            'password' => bcrypt('password'),
            'role' => 'author',
        ]);
    }

    protected function createReader(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test Reader',
            'email' => 'reader'.Str::random(8).'@example.com',
            'password' => bcrypt('password'),
            'role' => 'reader',
        ], $overrides));
    }

    protected function createAdmin(): User
    {
        return User::create([
            'name' => 'Test Admin',
            'email' => 'admin'.Str::random(8).'@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    /**
     * @return array{story: Story, episodes: \Illuminate\Support\Collection<int,Episode>}
     */
    protected function createStoryWithEpisodes(array $storyOverrides = [], int $episodeCount = 6): array
    {
        $category = Category::create(['name' => 'Fantasy', 'slug' => 'fantasy-'.Str::random(6)]);

        $penName = PenName::create([
            'user_id' => $storyOverrides['author_id'] ?? $this->createAuthor()->id,
            'display_name' => 'Pen '.Str::random(6),
            'slug' => 'pen-'.Str::random(8),
            'is_default' => true,
        ]);

        $story = Story::create(array_merge([
            'pen_name_id' => $penName->id,
            'category_id' => $category->id,
            'title' => 'Test Story',
            'slug' => 'test-story-'.Str::random(8),
            'description' => 'A story for testing.',
            'cover_image_url' => 'https://example.com/cover.jpg',
            'status' => 'published',
            'access_type' => 'premium',
            'published_at' => now(),
        ], array_diff_key($storyOverrides, ['author_id' => null])));

        $episodes = collect(range(1, $episodeCount))->map(fn (int $n) => Episode::create([
            'story_id' => $story->id,
            'title' => "Episode {$n}",
            'episode_number' => $n,
            'content' => "Content for episode {$n}.",
            'word_count' => 500,
            'status' => 'published',
            'published_at' => now(),
        ]));

        return ['story' => $story, 'episodes' => $episodes];
    }
}

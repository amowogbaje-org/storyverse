<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Models\Episode;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Implements the contract documented in storyverse-api-docs.md (supplied by
 * CraftProfessor, the consumer of this endpoint - we don't control that
 * contract, only how we satisfy it). Two things worth flagging that aren't
 * just "implement the doc":
 *
 * 1. {slug} is treated as the STORY's slug (StoryVerse's real public URL is
 *    /stories/{slug} - there's no per-episode slug in this app's routing,
 *    unlike the doc's own example URL scheme, which assumes one). Episode
 *    identity for CraftProfessor comes entirely from each episode's `url`
 *    instead, which the spec already says is fine ("not used programmatically
 *    ... include it anyway for logs").
 *
 * 2. Deliberately does NOT return every episode's full `content` on every
 *    call, even though the series/episode metadata (number, title, url,
 *    published_at) is always complete. A series with hundreds of episodes
 *    would otherwise mean re-encoding hundreds of full episode bodies into
 *    JSON on every single import/refresh call - real timeout risk, and mostly
 *    wasted work re-sending text CraftProfessor already has unchanged. The
 *    spec explicitly allows this: "If omitted or empty, CraftProfessor leaves
 *    any existing text untouched." So each call sends full content for up to
 *    self::CONTENT_BATCH_SIZE episodes - whichever most urgently need it
 *    (never synced, or edited since their last sync), most recent first - and
 *    metadata-only (content: null) for the rest. Since re-imports are
 *    explicitly expected ("after publishing episode 3"), the backlog clears
 *    itself over however many calls it takes; nothing is ever permanently
 *    excluded from the export.
 */
class CraftProfessorExportController
{
    public function show(Request $request, string $slug): JsonResponse
    {
        $story = Story::where('slug', $slug)->where('status', 'published')->first();

        if (! $story) {
            return response()->json(['message' => 'Story not found.'], 404);
        }

        $episodes = $story->publishedEpisodes()->orderBy('episode_number')->get();

        $batchSize = (int) config('craftprofessor.content_batch_size');

        $needsContent = $episodes
            ->filter(fn (Episode $e) => $e->content_synced_at === null || $e->updated_at->gt($e->content_synced_at))
            ->sortByDesc('episode_number')
            ->take($batchSize);

        if ($needsContent->isNotEmpty()) {
            Episode::whereIn('id', $needsContent->pluck('id'))->update(['content_synced_at' => now()]);
        }

        $includeContentFor = $needsContent->pluck('id')->all();
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return response()->json([
            'series' => [
                'title' => $story->title,
                'slug' => $story->slug,
                'description' => $story->description,
                'cover_image_url' => $story->cover_image_url,
                'url' => "{$frontendUrl}/stories/{$story->slug}",
            ],
            'episodes' => $episodes->map(fn (Episode $e) => [
                'number' => $e->episode_number,
                'title' => $e->title,
                // Cosmetic only (per spec, not used programmatically by CraftProfessor)
                // - StoryVerse doesn't have a real per-episode slug to give it.
                'slug' => "{$story->slug}-{$e->episode_number}-".Str::slug($e->title),
                'url' => "{$frontendUrl}/stories/{$story->slug}/episodes/{$e->episode_number}",
                'content' => in_array($e->id, $includeContentFor, true) ? $e->content : null,
                'published_at' => $e->published_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}

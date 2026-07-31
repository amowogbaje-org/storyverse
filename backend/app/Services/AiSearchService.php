<?php

namespace App\Services;

use App\Ai\Agents\StorySearchAgent;
use App\Models\Story;
use Illuminate\Support\Facades\Log;

/**
 * "AI search so users/readers can use AI to search for novels but this
 * shouldn't erase the native ways of searching" — per the project brief.
 * This is a separate service/endpoint from SearchController::native() by
 * design, so an AI provider outage or slow response never touches native
 * search - every failure here degrades to "no AI results", never an
 * exception the caller has to handle specially.
 *
 * Built on the Laravel AI SDK (see App\Ai\Agents\StorySearchAgent) rather than
 * calling a provider's HTTP API directly - provider-agnostic by design, since
 * which provider is actually in use (Gemini today) is configured, not
 * hardcoded, and can change without touching this file.
 */
class AiSearchService
{
    private const MAX_CATALOG_SIZE = 300;
    private const MAX_RESULTS = 10;

    /**
     * @return array{results: array<int, array{slug: string, reason: string}>, degraded: bool}
     */
    public function search(string $query): array
    {
        $catalog = Story::where('status', 'published')
            ->with(['categories', 'genres'])
            ->limit(self::MAX_CATALOG_SIZE)
            ->get()
            ->map(fn (Story $s) => [
                'slug' => $s->slug,
                'title' => $s->title,
                'description' => $s->description,
                'categories' => $s->categories->pluck('name'),
                'genres' => $s->genres->pluck('name'),
                'access_type' => $s->access_type,
            ]);

        if ($catalog->isEmpty()) {
            return ['results' => [], 'degraded' => false];
        }

        try {
            $response = StorySearchAgent::make(catalog: $catalog)->prompt($query, timeout: 15);

            $results = collect($response['results'] ?? [])
                ->filter(fn ($r) => isset($r['slug'], $r['reason']))
                ->take(self::MAX_RESULTS)
                ->values()
                ->all();

            return ['results' => $results, 'degraded' => false];
        } catch (\Throwable $e) {
            // Covers a genuinely down provider, a missing/invalid API key for
            // whichever provider is configured, a timeout, or a malformed
            // structured-output response - caller falls back to native search
            // either way, so the exact cause only matters for the log line.
            Log::warning('AI search failed, caller should fall back to native search', ['error' => $e->getMessage()]);

            return ['results' => [], 'degraded' => true];
        }
    }
}

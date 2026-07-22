<?php

namespace App\Services;

use App\Models\Story;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * "AI search so users/readers can use AI to search for novels but this
 * shouldn't erase the native ways of searching" — per the project brief.
 * This is a separate service/endpoint from SearchController::native() by
 * design, so an OpenAI outage or slow response never touches native search.
 *
 * Approach: rather than a full embeddings pipeline (extra infra: a vector
 * column, a reindex job, a similarity search), this sends the catalog
 * directly to a chat completion and asks it to rank + explain matches. That's
 * the right tradeoff at "just starting out" catalog sizes (hundreds of
 * stories fit comfortably in one prompt) and is dramatically simpler to run
 * correctly. Once the catalog is large enough that one prompt can't hold it,
 * swap this for embeddings + pgvector (or Meilisearch's built-in AI search,
 * since laravel/scout + meilisearch-php are already in composer.json).
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
        $apiKey = config('services.openai.api_key');

        if (! $apiKey) {
            return ['results' => [], 'degraded' => true];
        }

        $catalog = Story::where('status', 'published')
            ->with(['category', 'genres'])
            ->limit(self::MAX_CATALOG_SIZE)
            ->get()
            ->map(fn (Story $s) => [
                'slug' => $s->slug,
                'title' => $s->title,
                'description' => $s->description,
                'category' => $s->category?->name,
                'genres' => $s->genres->pluck('name'),
                'access_type' => $s->access_type,
            ]);

        if ($catalog->isEmpty()) {
            return ['results' => [], 'degraded' => false];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => json_encode([
                            'query' => $query,
                            'catalog' => $catalog,
                        ])],
                    ],
                ])
                ->throw()
                ->json();

            $content = $response['choices'][0]['message']['content'] ?? '{}';
            $parsed = json_decode($content, true);
            $results = collect($parsed['results'] ?? [])
                ->filter(fn ($r) => isset($r['slug'], $r['reason']))
                ->take(self::MAX_RESULTS)
                ->values()
                ->all();

            return ['results' => $results, 'degraded' => false];
        } catch (\Throwable $e) {
            Log::warning('AI search failed, caller should fall back to native search', ['error' => $e->getMessage()]);

            return ['results' => [], 'degraded' => true];
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are the search assistant for a serialized-fiction reading app. You'll receive
        a JSON object with a "query" (a freeform description of what the reader wants to
        read) and a "catalog" (published stories with title, description, category, genres).

        Pick up to 10 stories from the catalog that best match the query, ranked best-first.
        Only include stories that are genuinely relevant - if fewer than 10 fit, return fewer.
        Never invent a story that isn't in the catalog.

        Respond with strict JSON only, in this exact shape:
        {"results": [{"slug": "story-slug", "reason": "one short sentence on why this fits"}]}
        PROMPT;
    }
}

<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * "AI search so users/readers can use AI to search for novels but this
 * shouldn't erase the native ways of searching" — per the project brief.
 * Used from a separate service/endpoint from SearchController::native() by
 * design, so a provider outage or slow response never touches native search
 * (see AiSearchService, which wraps every call to this agent in a try/catch).
 *
 * Deliberately provider-agnostic: which AI provider this uses is read from
 * config('services.ai_search.provider') in provider() below, not hardcoded
 * via a #[Provider] attribute. Today that's Gemini; switching to OpenAI (or
 * anything else the Laravel AI SDK supports) later is just setting
 * AI_SEARCH_PROVIDER and that provider's API key in .env - no code change,
 * no redeploy.
 *
 * Approach: rather than a full embeddings pipeline (extra infra: a vector
 * column, a reindex job, a similarity search), the catalog is sent directly
 * in the prompt and the model is asked to rank + explain matches, using the
 * SDK's structured output (HasStructuredOutput below) instead of manually
 * parsing free-form JSON out of a text response. That's the right tradeoff at
 * "just starting out" catalog sizes (hundreds of stories fit comfortably in
 * one prompt) and is dramatically simpler to run correctly. Once the catalog
 * is large enough that one prompt can't hold it, swap this for the SDK's
 * embeddings + SimilaritySearch tool (or Meilisearch's built-in AI search,
 * since laravel/scout + meilisearch-php are already in composer.json).
 */
class StorySearchAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /** @param Collection<int, array{slug: string, title: string, description: ?string, categories: mixed, genres: mixed, access_type: string}> $catalog */
    public function __construct(public Collection $catalog) {}

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        You are the search assistant for a serialized-fiction reading app.

        Below is the current catalog of published stories, as JSON (slug, title,
        description, categories, genres, access_type - a story can belong to more
        than one category and genre):

        {$this->catalog->toJson()}

        You'll be given a reader's freeform description of what they want to read.
        Pick up to 10 stories from the catalog above that best match, ranked
        best-first. Only include stories that are genuinely relevant - if fewer
        than 10 fit, return fewer. Never invent a story that isn't in the catalog.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'results' => $schema->array()->items(
                $schema->object([
                    'slug' => $schema->string()->required(),
                    'reason' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }

    public function provider(): Lab|string
    {
        return config('services.ai_search.provider', 'gemini');
    }
}

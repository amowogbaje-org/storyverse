<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Adds tasteful inline formatting (bold, italics, scene breaks, etc) to an
 * episode's raw, unstyled text - see the styling pipeline in StyleEpisodes.
 *
 * The markup it's instructed to use is deliberately the exact syntax
 * frontend/src/components/reader/RichText.jsx already knows how to render
 * (**bold**, *italic*, ~~strike~~, "> " quotes, a lone "***" scene break) -
 * whichever provider is behind this, its output has to round-trip through
 * that renderer, so the contract lives in both places and has to stay in
 * sync if either changes.
 *
 * Provider-agnostic by design, same as StorySearchAgent: which Laravel AI
 * SDK provider actually handles this is read from
 * config('services.episode_styling.provider') in provider() below, not
 * hardcoded - switching between Gemini/Ollama/DeepSeek/anything else the SDK
 * supports is just that env var (plus that provider's key in config/ai.php),
 * no code change.
 */
class EpisodeStylingAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a copy-editor for a serialized fiction app. You will be given
        the raw, unstyled text of one episode. Your only job is to add light
        inline formatting that makes it more engaging to read - you are not a
        writer or an editor of the prose itself.

        Rules, all mandatory:
        1. Do not change, add, remove, or reorder a single word. The wordcount
           and meaning of the output must exactly match the input.
        2. Do not fix spelling, grammar, or punctuation, even if it looks wrong.
        3. Use only this markup, applied where it genuinely earns its place
           (internal thoughts, emphasis, foreign/invented words, letters or
           notes read within the story, a hard scene change):
             **bold** for strong emphasis
             *italic* for internal thought, emphasis, or foreign/invented words
             ~~strikethrough~~ only if the original text itself uses it
             > at the start of a line for an in-story letter, note, or quoted
               document (not for normal dialogue)
             a lone line containing exactly *** for a scene break
        4. Use formatting sparingly - a handful of touches per episode, not
           every paragraph. Most sentences should be untouched.
        5. Preserve the original paragraph breaks exactly as given.
        6. Output ONLY the styled episode text. No preamble, no explanation,
           no markdown code fence, nothing before or after it.
        PROMPT;
    }

    public function provider(): Lab|string
    {
        return config('services.episode_styling.provider', 'gemini');
    }
}

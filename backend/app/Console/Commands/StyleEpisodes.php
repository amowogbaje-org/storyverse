<?php

namespace App\Console\Commands;

use App\Ai\Agents\EpisodeStylingAgent;
use App\Models\Episode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sends unstyled episodes to the AI styling agent (see EpisodeStylingAgent),
 * batch_size at a time (10 by default), and writes the result back to
 * `content` - `raw_content` is only ever read here, never written, so the
 * author's original text can't be lost even if styling misbehaves.
 * Scheduled every 15 minutes in routes/console.php.
 *
 * An episode enters the queue by having raw_content set and styled_at null
 * (see EpisodeManagementController, which resets styled_at on every author
 * edit) and leaves it either by being styled successfully, or by exhausting
 * config('services.episode_styling.max_attempts') - at which point it falls
 * back to showing the raw text rather than retrying forever.
 */
class StyleEpisodes extends Command
{
    protected $signature = 'app:style-episodes';

    protected $description = 'Send unstyled episodes to the AI styling agent and save the styled result';

    public function handle(): int
    {
        $batchSize = (int) config('services.episode_styling.batch_size', 10);
        $maxAttempts = (int) config('services.episode_styling.max_attempts', 5);

        $episodes = Episode::whereNotNull('raw_content')
            ->whereNull('styled_at')
            ->orderBy('raw_content_updated_at')
            ->limit($batchSize)
            ->get();

        if ($episodes->isEmpty()) {
            $this->info('No episodes waiting to be styled.');

            return self::SUCCESS;
        }

        foreach ($episodes as $episode) {
            $this->styleOne($episode, $maxAttempts);
        }

        return self::SUCCESS;
    }

    private function styleOne(Episode $episode, int $maxAttempts): void
    {
        try {
            $response = EpisodeStylingAgent::make()->prompt($episode->raw_content, timeout: 60);

            // prompt() always returns a response object for an agent with no
            // structured-output schema, never a plain string - confirmed
            // against the Laravel AI SDK docs (every example reads it via
            // ->text() or a (string) cast, e.g. `echo $response->text();`).
            // (string) is used here rather than ->text() since the docs also
            // show the response object implementing __toString() directly
            // (`'reply' => (string) $response`), which is one call cheaper
            // and doesn't assume a specific response class.
            $styled = trim((string) $response);

            if ($styled === '') {
                throw new \RuntimeException('Styling agent returned an empty response.');
            }

            Episode::where('id', $episode->id)->update([
                'content' => $styled,
                'styled_at' => now(),
                'styling_attempts' => 0,
            ]);

            $this->info("Styled episode #{$episode->id}.");
        } catch (\Throwable $e) {
            $attempts = $episode->styling_attempts + 1;

            Log::warning('Episode styling attempt failed', [
                'episode_id' => $episode->id,
                'attempt' => $attempts,
                'error' => $e->getMessage(),
            ]);

            if ($attempts >= $maxAttempts) {
                // Give up gracefully: readers get the unstyled text (no worse
                // than before this feature existed) instead of this episode
                // silently never appearing correctly, or eating a styling
                // attempt every 15 minutes forever.
                Episode::where('id', $episode->id)->update([
                    'content' => $episode->raw_content,
                    'styled_at' => now(),
                    'styling_attempts' => $attempts,
                ]);

                Log::warning('Episode styling gave up after max attempts, falling back to raw text', [
                    'episode_id' => $episode->id,
                    'attempts' => $attempts,
                ]);

                $this->warn("Episode #{$episode->id} failed {$attempts}x - falling back to raw text.");
            } else {
                Episode::where('id', $episode->id)->update(['styling_attempts' => $attempts]);

                $this->warn("Episode #{$episode->id} failed (attempt {$attempts}/{$maxAttempts}), will retry next run.");
            }
        }
    }
}

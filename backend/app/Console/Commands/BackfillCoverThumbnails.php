<?php

namespace App\Console\Commands;

use App\Models\Story;
use App\Services\ImageOptimizerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * One-time (well - rerunnable, but idempotent) fill-in for stories saved
 * before cover_image_thumb_url existed (see that migration's docblock).
 * StoryCardPresenter::card() already falls back to the full image for these,
 * so nothing is broken without this - running it is what actually realizes
 * the smaller-homepage-payload win for stories already in the catalog,
 * rather than only new uploads going forward.
 *
 * Fetches cover_image_url over HTTP rather than assuming it's a local
 * storage path: some stories may have a cover pasted in via the admin's
 * "Image URL" mode (an external URL, not one of ours), and this handles
 * both the same way rather than needing to special-case local vs. external.
 */
class BackfillCoverThumbnails extends Command
{
    protected $signature = 'stories:backfill-cover-thumbnails {--limit=0 : Stop after this many (0 = no limit)}';

    protected $description = 'Generate cover_image_thumb_url for stories that only have a full-size cover_image_url';

    public function handle(ImageOptimizerService $optimizer): int
    {
        $limit = (int) $this->option('limit');

        $query = Story::whereNotNull('cover_image_url')
            ->whereNull('cover_image_thumb_url')
            ->orderBy('id');

        $total = $query->count();
        if ($total === 0) {
            $this->info('Nothing to backfill - every story already has a thumbnail.');

            return self::SUCCESS;
        }

        $this->info("Backfilling thumbnails for {$total} stor".($total === 1 ? 'y' : 'ies').'...');
        $bar = $this->output->createProgressBar($limit > 0 ? min($limit, $total) : $total);
        $done = 0;
        $failed = 0;

        $query->chunkById(50, function ($stories) use ($optimizer, &$done, &$failed, $bar, $limit) {
            foreach ($stories as $story) {
                if ($limit > 0 && $done >= $limit) {
                    return false; // stop chunking
                }

                try {
                    $response = Http::timeout(15)->get($story->cover_image_url);

                    if (! $response->successful()) {
                        throw new \RuntimeException("HTTP {$response->status()}");
                    }

                    $thumb = $optimizer->thumbnailFromContents($response->body());
                    $path = 'covers/'.$story->id.'-backfill-thumb.'.$thumb['extension'];
                    Storage::disk('public')->put($path, $thumb['contents']);
                    $story->update(['cover_image_thumb_url' => Storage::disk('public')->url($path)]);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("Story #{$story->id} ({$story->slug}): {$e->getMessage()}");
                }

                $done++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done: {$done} processed, ".($done - $failed)." succeeded, {$failed} failed.");

        if ($failed > 0) {
            $this->comment('Failed stories keep falling back to their full-size cover_image_url (see StoryCardPresenter) - safe to leave as-is or re-run this command later.');
        }

        return self::SUCCESS;
    }
}

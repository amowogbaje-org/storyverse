<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Deliberately built on plain GD rather than intervention/image or similar -
 * GD ships enabled by default on virtually every PHP install, including
 * shared cPanel hosting where you can't always get a host to enable Imagick
 * or install extra extensions. One less thing to depend on.
 *
 * Always re-encodes to JPEG: universally supported by every GD build (unlike
 * WebP, which isn't guaranteed on every shared host), and story covers are
 * photographic/illustrated art where JPEG compresses well.
 */
class ImageOptimizerService
{
    private const MAX_WIDTH = 1000;
    private const MAX_HEIGHT = 1400; // ~5:7, comfortable for a portrait book-cover crop
    private const JPEG_QUALITY = 82;

    // Grid cards (StoryCard, homepage/browse) render covers at roughly
    // 150-300 CSS px wide in a 3:4 box - this comfortably covers that at 2x
    // pixel density without shipping the same 1000x1400/~100-150KB image to
    // every card on a page that might show 20+ of them at once. Lower JPEG
    // quality too: a thumbnail this small doesn't need the same fidelity a
    // full-page hero cover does, and compression artifacts are far less
    // visible at this size.
    private const THUMB_WIDTH = 360;
    private const THUMB_HEIGHT = 504;
    private const THUMB_JPEG_QUALITY = 72;

    /**
     * @return array{full: array{contents: string, extension: string}, thumb: array{contents: string, extension: string}}
     */
    public function optimizeCoverImage(UploadedFile $file): array
    {
        $source = @imagecreatefromstring(file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('Could not read this file as an image.');
        }

        $width = imagesx($source);
        $height = imagesy($source);

        $full = $this->resample($source, $width, $height, self::MAX_WIDTH, self::MAX_HEIGHT, self::JPEG_QUALITY);
        $thumb = $this->resample($source, $width, $height, self::THUMB_WIDTH, self::THUMB_HEIGHT, self::THUMB_JPEG_QUALITY);

        imagedestroy($source);

        return ['full' => $full, 'thumb' => $thumb];
    }

    /**
     * Used by the stories:backfill-cover-thumbnails command - the full-size
     * image already exists (cover_image_url), so this only needs to produce
     * the thumb variant from bytes already fetched, not go through the
     * two-variant upload flow above.
     *
     * @return array{contents: string, extension: string}
     */
    public function thumbnailFromContents(string $contents): array
    {
        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('Could not read this file as an image.');
        }

        $thumb = $this->resample($source, imagesx($source), imagesy($source), self::THUMB_WIDTH, self::THUMB_HEIGHT, self::THUMB_JPEG_QUALITY);
        imagedestroy($source);

        return $thumb;
    }

    /** @return array{contents: string, extension: string} */
    private function resample($source, int $width, int $height, int $maxWidth, int $maxHeight, int $quality): array
    {
        $scale = min(1, $maxWidth / $width, $maxHeight / $height);

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        // Flatten onto white (covers a transparent PNG upload turning into a JPEG,
        // which has no alpha channel - transparent areas would otherwise go black).
        $white = imagecolorallocate($resized, 255, 255, 255);
        imagefill($resized, 0, 0, $white);
        imagealphablending($resized, true);

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($resized, null, $quality);
        $contents = ob_get_clean();
        imagedestroy($resized);

        return ['contents' => $contents, 'extension' => 'jpg'];
    }
}

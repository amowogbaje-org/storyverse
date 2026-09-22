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

    /** @return array{contents: string, extension: string} */
    public function optimizeCoverImage(UploadedFile $file): array
    {
        $source = @imagecreatefromstring(file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('Could not read this file as an image.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        // Flatten onto white (covers a transparent PNG upload turning into a JPEG,
        // which has no alpha channel - transparent areas would otherwise go black).
        $white = imagecolorallocate($resized, 255, 255, 255);
        imagefill($resized, 0, 0, $white);
        imagealphablending($resized, true);

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        ob_start();
        imagejpeg($resized, null, self::JPEG_QUALITY);
        $contents = ob_get_clean();
        imagedestroy($resized);

        return ['contents' => $contents, 'extension' => 'jpg'];
    }
}

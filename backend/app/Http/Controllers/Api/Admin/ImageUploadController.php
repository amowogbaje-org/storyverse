<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageOptimizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    public function coverImage(Request $request, ImageOptimizerService $optimizer)
    {
        $request->validate([
            // 8MB ceiling on the upload itself; the optimizer then re-encodes and
            // resizes it down to something web-appropriate before it's stored.
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        try {
            $optimized = $optimizer->optimizeCoverImage($request->file('image'));
        } catch (\RuntimeException $e) {
            return $this->error('invalid_image', $e->getMessage(), 422);
        }

        $id = Str::uuid();
        $fullPath = "covers/{$id}.".$optimized['full']['extension'];
        $thumbPath = "covers/{$id}-thumb.".$optimized['thumb']['extension'];

        Storage::disk('public')->put($fullPath, $optimized['full']['contents']);
        Storage::disk('public')->put($thumbPath, $optimized['thumb']['contents']);

        return $this->ok([
            'url' => Storage::disk('public')->url($fullPath),
            // See StoryCardPresenter::card()'s fallback - a story saved
            // without going through this endpoint (the admin's "paste a URL"
            // option) simply won't have a thumb_url, and grid cards fall
            // back to the full image rather than breaking.
            'thumb_url' => Storage::disk('public')->url($thumbPath),
        ], 201);
    }
}

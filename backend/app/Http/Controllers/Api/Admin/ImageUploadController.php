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

        $filename = 'covers/'.Str::uuid().'.'.$optimized['extension'];
        Storage::disk('public')->put($filename, $optimized['contents']);

        return $this->ok(['url' => Storage::disk('public')->url($filename)], 201);
    }
}

<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Services\ImageOptimizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    public function store(Request $request, ImageOptimizerService $optimizer)
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'folder' => ['required', 'in:covers,avatars'],
        ]);

        try {
            $optimized = $optimizer->optimizeCoverImage($request->file('image'));
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $filename = $request->input('folder').'/'.Str::uuid().'.'.$optimized['extension'];
        Storage::disk('public')->put($filename, $optimized['contents']);

        return response()->json(['url' => Storage::disk('public')->url($filename)], 201);
    }
}

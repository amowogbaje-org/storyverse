<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PenName;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * "Authors ... should also be able to create multiple pen names just in case
 * they want to be dynamic" — per the project brief. Admins can see/manage any
 * author's pen names too, for moderation.
 */
class PenNameManagementController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->requireUser($request);

        $penNames = $user->role === 'admin'
            ? PenName::with('user:id,name')->get()
            : $user->penNames;

        return $this->ok($penNames);
    }

    public function store(Request $request)
    {
        $user = $this->requireUser($request);

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            $user->penNames()->update(['is_default' => false]);
        }

        $penName = $user->penNames()->create([
            ...$data,
            'slug' => $this->uniqueSlug($data['display_name']),
            'is_default' => $request->boolean('is_default') || $user->penNames()->count() === 0,
        ]);

        return $this->ok($penName, 201);
    }

    public function update(Request $request, int $id)
    {
        $user = $this->requireUser($request);
        $penName = PenName::findOrFail($id);

        if ($penName->user_id !== $user->id && $user->role !== 'admin') {
            return $this->error('forbidden', 'You do not own this pen name.', 403);
        }

        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            $penName->user->penNames()->update(['is_default' => false]);
        }

        $penName->update($data);

        return $this->ok($penName);
    }

    private function uniqueSlug(string $displayName): string
    {
        $base = Str::slug($displayName);
        $slug = $base;
        $i = 1;

        while (PenName::where('slug', $slug)->exists()) {
            $slug = "{$base}-".(++$i);
        }

        return $slug;
    }
}

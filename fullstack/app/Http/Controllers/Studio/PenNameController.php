<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\PenName;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PenNameController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $penNames = $user->role === 'admin' ? PenName::with('user:id,name')->get() : $user->penNames;

        return view('studio.pen-names.index', compact('penNames'));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            $user->penNames()->update(['is_default' => false]);
        }

        $user->penNames()->create([
            ...$data,
            'slug' => $this->uniqueSlug($data['display_name']),
            'is_default' => $request->boolean('is_default') || $user->penNames()->count() === 0,
        ]);

        return redirect()->route('studio.pen-names.index')->with('status', 'Pen name created.');
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();
        $penName = PenName::findOrFail($id);

        abort_unless($penName->user_id === $user->id || $user->role === 'admin', 403, 'You do not own this pen name.');

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            $penName->user->penNames()->update(['is_default' => false]);
        }

        $penName->update($data);

        return redirect()->route('studio.pen-names.index')->with('status', 'Pen name updated.');
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

@extends('layouts.app')

@section('title', 'Pen names - Studio')

@section('content')
    <h1 class="mb-6 font-serif text-2xl font-semibold">Pen names</h1>

    <div class="mb-8 grid gap-4 sm:grid-cols-2">
        @foreach($penNames as $penName)
            <form method="POST" action="{{ route('studio.pen-names.update', $penName->id) }}" class="space-y-2 rounded-xl border border-stone-200 bg-white p-4">
                @csrf
                @method('PATCH')
                <input type="text" name="display_name" value="{{ $penName->display_name }}" required
                       class="w-full rounded-lg border border-stone-300 px-3 py-1.5 text-sm font-medium">
                <textarea name="bio" rows="2" placeholder="Short bio"
                          class="w-full rounded-lg border border-stone-300 px-3 py-1.5 text-sm">{{ $penName->bio }}</textarea>
                <input type="text" name="avatar_url" value="{{ $penName->avatar_url }}" placeholder="Avatar URL"
                       class="w-full rounded-lg border border-stone-300 px-3 py-1.5 text-xs">
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-1.5 text-xs text-stone-500">
                        <input type="checkbox" name="is_default" value="1" @checked($penName->is_default)> Default
                    </label>
                    <button type="submit" class="rounded-full bg-stone-900 px-3 py-1 text-xs text-white">Save</button>
                </div>
            </form>
        @endforeach
    </div>

    <h2 class="mb-3 font-serif text-lg font-semibold">New pen name</h2>
    <form method="POST" action="{{ route('studio.pen-names.store') }}" class="max-w-md space-y-3">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium">Display name</label>
            <input type="text" name="display_name" required class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Bio</label>
            <textarea name="bio" rows="2" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm"></textarea>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Avatar URL</label>
            <input type="text" name="avatar_url" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
        </div>
        <label class="flex items-center gap-1.5 text-sm text-stone-600">
            <input type="checkbox" name="is_default" value="1"> Make default
        </label>
        <button type="submit" class="rounded-full bg-stone-900 px-4 py-2 text-sm font-medium text-white">Create pen name</button>
    </form>
@endsection

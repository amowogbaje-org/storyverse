@extends('layouts.app')

@section('title', 'Your profile - Storyverse')

@section('content')
    <div class="mx-auto max-w-md space-y-8">
        <div>
            <h1 class="mb-4 font-serif text-2xl font-semibold">Your profile</h1>
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="mb-1 block text-sm font-medium">Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Currency</label>
                    <select name="currency" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                        @foreach($currencies as $code => $c)
                            <option value="{{ $code }}" @selected($user->currency === $code)>{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-full bg-stone-900 px-4 py-2 text-sm font-medium text-white">Save changes</button>
            </form>
        </div>

        <div>
            <h2 class="mb-4 font-serif text-lg font-semibold">Change password</h2>
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="mb-1 block text-sm font-medium">Current password</label>
                    <input type="password" name="current_password" required
                           class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">New password</label>
                    <input type="password" name="password" required minlength="8"
                           class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Confirm new password</label>
                    <input type="password" name="password_confirmation" required
                           class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="rounded-full bg-stone-900 px-4 py-2 text-sm font-medium text-white">Update password</button>
            </form>
        </div>

        @if($user->role === 'reader')
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm font-medium">Want to publish your own stories?</p>
                @if(($user->author_request_status ?? 'none') === 'pending')
                    <p class="mt-1 text-xs text-stone-600">
                        Request sent{{ $user->author_requested_at ? ' on '.$user->author_requested_at->format('M j, Y') : '' }} - waiting on an admin.
                    </p>
                @else
                    @if(($user->author_request_status ?? 'none') === 'rejected')
                        <p class="mt-1 text-xs text-red-600">Your last request wasn't approved.</p>
                    @endif
                    <form method="POST" action="{{ route('profile.request-author') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="rounded-full bg-amber-500 px-4 py-1.5 text-sm font-medium text-white">
                            {{ ($user->author_request_status ?? 'none') === 'rejected' ? 'Request again' : 'Request author access' }}
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Reset your password - Storyverse')

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="mb-6 font-serif text-2xl font-semibold">Reset your password</h1>
        <form method="POST" action="{{ route('auth.reset-password') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <div>
                <label class="mb-1 block text-sm font-medium">Reset code</label>
                <input type="text" name="code" inputmode="numeric" required
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-center text-lg tracking-[0.5em]">
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
            <button type="submit" class="w-full rounded-full bg-stone-900 px-4 py-2 text-sm font-medium text-white">Reset password</button>
        </form>
    </div>
@endsection

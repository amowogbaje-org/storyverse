@extends('layouts.app')

@section('title', 'Verify your email - Storyverse')

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="mb-2 font-serif text-2xl font-semibold">Check your email</h1>
        <p class="mb-6 text-sm text-stone-600">
            We sent a 6-digit code to <strong>{{ $email }}</strong>. Enter it below to verify your account.
        </p>

        <form method="POST" action="{{ route('auth.verify') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <div>
                <label class="mb-1 block text-sm font-medium">Verification code</label>
                <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-center text-lg tracking-[0.5em]">
            </div>
            <button type="submit" class="w-full rounded-full bg-stone-900 px-4 py-2 text-sm font-medium text-white">Verify</button>
        </form>

        <form method="POST" action="{{ route('auth.verify.resend') }}" class="mt-4 text-center">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <button type="submit" class="text-sm text-stone-500 hover:text-stone-900">Resend code</button>
        </form>
    </div>
@endsection

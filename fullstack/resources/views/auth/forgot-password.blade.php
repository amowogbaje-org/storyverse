@extends('layouts.app')

@section('title', 'Reset your password - Storyverse')

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="mb-2 font-serif text-2xl font-semibold">Forgot your password?</h1>
        <p class="mb-6 text-sm text-stone-600">Enter your email and we'll send you a reset code.</p>
        <form method="POST" action="{{ route('auth.forgot-password') }}" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <button type="submit" class="w-full rounded-full bg-stone-900 px-4 py-2 text-sm font-medium text-white">Send reset code</button>
        </form>
    </div>
@endsection

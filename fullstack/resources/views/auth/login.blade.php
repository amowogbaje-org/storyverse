@extends('layouts.app')

@section('title', 'Sign in - Storyverse')

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="mb-6 font-serif text-2xl font-semibold">Sign in</h1>
        <form method="POST" action="{{ route('auth.login') }}" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Password</label>
                <input type="password" name="password" required
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" name="remember"> Keep me signed in
            </label>
            <button type="submit" class="w-full rounded-full bg-stone-900 px-4 py-2 text-sm font-medium text-white">Sign in</button>
        </form>
        <div class="mt-4 flex justify-between text-sm text-stone-500">
            <a href="{{ route('auth.forgot-password.show') }}" class="hover:text-stone-900">Forgot password?</a>
            <a href="{{ route('auth.register.show') }}" class="hover:text-stone-900">Create an account</a>
        </div>
    </div>
@endsection

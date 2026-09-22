@extends('layouts.app')

@section('title', 'Create an account - Storyverse')

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="mb-6 font-serif text-2xl font-semibold">Create your account</h1>
        <form method="POST" action="{{ route('auth.register') }}" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Password</label>
                <input type="password" name="password" required minlength="8"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Confirm password</label>
                <input type="password" name="password_confirmation" required
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <label class="flex items-start gap-2 text-sm text-stone-600">
                <input type="checkbox" name="accept_terms" value="1" required class="mt-0.5">
                <span>I agree to the Terms of Service and Privacy Policy.</span>
            </label>
            <button type="submit" class="w-full rounded-full bg-stone-900 px-4 py-2 text-sm font-medium text-white">Create account</button>
        </form>
        <p class="mt-4 text-center text-sm text-stone-500">
            Already have an account? <a href="{{ route('auth.login.show') }}" class="font-medium text-stone-900">Sign in</a>
        </p>
    </div>
@endsection

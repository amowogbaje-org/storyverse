<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Storyverse')</title>
    <meta name="description" content="@yield('description', 'Read and publish serialized fiction on Storyverse.')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Source+Serif+4:wght@500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-stone-50 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
            <a href="{{ route('home') }}" class="font-serif text-xl font-semibold tracking-tight">Storyverse</a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ route('stories.index') }}" class="text-stone-600 hover:text-stone-900">Browse</a>
                @auth
                    @if(in_array(auth()->user()->role, ['author', 'admin']))
                        <a href="{{ route('studio.dashboard') }}" class="text-stone-600 hover:text-stone-900">Studio</a>
                    @endif
                    @if(auth()->user()->role === 'admin')
                        <a href="{{ route('admin.users.index') }}" class="text-stone-600 hover:text-stone-900">Users</a>
                    @endif
                    <a href="{{ route('profile.show') }}" class="text-stone-600 hover:text-stone-900">{{ auth()->user()->name }}</a>
                    <form method="POST" action="{{ route('auth.logout') }}">
                        @csrf
                        <button type="submit" class="text-stone-600 hover:text-stone-900">Sign out</button>
                    </form>
                @else
                    <a href="{{ route('auth.login.show') }}" class="text-stone-600 hover:text-stone-900">Sign in</a>
                    <a href="{{ route('auth.register.show') }}" class="rounded-full bg-stone-900 px-3 py-1.5 text-white hover:bg-stone-700">Join free</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mt-12 border-t border-stone-200 py-6 text-center text-xs text-stone-400">
        &copy; {{ date('Y') }} Storyverse
    </footer>
</body>
</html>

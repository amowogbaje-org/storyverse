@extends('layouts.app')

@section('title', 'Users - Admin')

@section('content')
    <h1 class="mb-6 font-serif text-2xl font-semibold">Users</h1>

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or email…"
               class="rounded-full border border-stone-300 px-4 py-1.5 text-sm">
        <select name="status" onchange="this.form.submit()" class="rounded-full border border-stone-300 px-3 py-1.5 text-sm">
            <option value="">Any request status</option>
            <option value="pending" @selected(request('status') === 'pending')>Pending</option>
            <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
        </select>
        <select name="role" onchange="this.form.submit()" class="rounded-full border border-stone-300 px-3 py-1.5 text-sm">
            <option value="">Any role</option>
            <option value="reader" @selected(request('role') === 'reader')>Reader</option>
            <option value="author" @selected(request('role') === 'author')>Author</option>
            <option value="admin" @selected(request('role') === 'admin')>Admin</option>
        </select>
        <button type="submit" class="rounded-full bg-stone-900 px-4 py-1.5 text-sm text-white">Filter</button>
    </form>

    <ul class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
        @forelse($users as $user)
            <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium">{{ $user->name }}</p>
                    <p class="truncate text-xs text-stone-500">{{ $user->email }}</p>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="rounded-full bg-stone-100 px-2 py-0.5 text-[11px] capitalize text-stone-600">{{ $user->role }}</span>
                        @if($user->author_request_status === 'pending')
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] text-amber-700">
                                Requested {{ $user->author_requested_at?->format('M j, Y') }}
                            </span>
                        @elseif($user->author_request_status === 'rejected')
                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] text-red-600">Declined</span>
                        @endif
                    </div>
                </div>
                <div class="flex shrink-0 gap-1.5">
                    @if(! in_array($user->role, ['author', 'admin']))
                        <form method="POST" action="{{ route('admin.users.grant-author', $user->id) }}">
                            @csrf
                            <button type="submit" class="rounded-full bg-stone-900 px-3 py-1 text-xs text-white">Grant author</button>
                        </form>
                    @endif
                    @if($user->author_request_status === 'pending')
                        <form method="POST" action="{{ route('admin.users.reject-author-request', $user->id) }}">
                            @csrf
                            <button type="submit" class="rounded-full border border-stone-300 px-3 py-1 text-xs">Decline</button>
                        </form>
                    @endif
                    @if($user->role === 'author')
                        <form method="POST" action="{{ route('admin.users.revoke-author', $user->id) }}"
                              onsubmit="return confirm('Revoke {{ $user->name }}\'s author access and unpublish their stories?')">
                            @csrf
                            <button type="submit" class="rounded-full border border-red-300 px-3 py-1 text-xs text-red-600">Revoke</button>
                        </form>
                    @endif
                </div>
            </li>
        @empty
            <li class="px-4 py-8 text-center text-sm text-stone-500">No users match this filter.</li>
        @endforelse
    </ul>

    <div class="mt-6">{{ $users->links() }}</div>
@endsection

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\User;
use App\Notifications\AuthorStatusChanged;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['sometimes', 'in:none,pending,rejected'],
            'role' => ['sometimes', 'in:reader,author,admin'],
            'q' => ['sometimes', 'string', 'max:255'],
        ]);

        $query = User::query();

        if (! empty($data['status'])) {
            $query->where('author_request_status', $data['status']);
        }
        if (! empty($data['role'])) {
            $query->where('role', $data['role']);
        }
        if (! empty($data['q'])) {
            $term = '%'.$data['q'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }

        $users = $query->orderByDesc('author_requested_at')->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'filters' => $data]);
    }

    public function grantAuthor(int $id)
    {
        $user = User::findOrFail($id);

        if ($user->role === 'admin') {
            return back()->withErrors(['user' => 'Admins already have full access.']);
        }

        $wasAlreadyAuthor = $user->role === 'author';
        $user->update(['role' => 'author', 'author_request_status' => 'none', 'author_requested_at' => null]);

        if (! $wasAlreadyAuthor) {
            $user->notify(new AuthorStatusChanged('granted'));
        }

        return back()->with('status', "{$user->name} is now an author.");
    }

    public function rejectAuthorRequest(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['author_request_status' => 'rejected']);
        $user->notify(new AuthorStatusChanged('rejected'));

        return back()->with('status', "{$user->name}'s request was declined.");
    }

    public function revokeAuthor(Request $request, int $id)
    {
        $data = $request->validate(['unpublish_stories' => ['sometimes', 'boolean']]);
        $user = User::findOrFail($id);

        if ($user->role !== 'author') {
            return back()->withErrors(['user' => 'This user is not currently an author.']);
        }

        $penNameIds = $user->penNames()->pluck('id');
        $unpublishedCount = 0;

        if ($data['unpublish_stories'] ?? true) {
            $unpublishedCount = Story::whereIn('pen_name_id', $penNameIds)->where('status', 'published')->count();
            Story::whereIn('pen_name_id', $penNameIds)->where('status', 'published')->update(['status' => 'draft']);

            if ($unpublishedCount > 0) {
                \App\Support\HomeCache::forgetHomepage();
            }
        }

        $user->update(['role' => 'reader', 'author_request_status' => 'none', 'author_requested_at' => null]);
        $user->notify(new AuthorStatusChanged('revoked'));

        return back()->with('status', "{$user->name} was moved back to reader.".($unpublishedCount ? " {$unpublishedCount} stories unpublished." : ''));
    }
}

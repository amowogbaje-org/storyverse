<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\User;
use App\Notifications\AuthorStatusChanged;
use Illuminate\Http\Request;

/**
 * Platform-admin-only (RequireAdmin, not the looser author_or_admin used
 * elsewhere in /admin): who gets to publish is a platform decision, not
 * something an author should be able to grant themselves or each other.
 *
 * Readers opt in by requesting (AuthController::requestAuthor); nothing here
 * ever flips a role without an admin explicitly choosing to.
 */
class UserManagementController extends Controller
{
    /**
     * Filterable so this same endpoint backs both the "pending requests"
     * inbox (?status=pending) and a general user browser (?role=author,
     * ?q=name-or-email) without needing separate routes for each.
     */
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

        $paginator = $query->orderByDesc('author_requested_at')->orderByDesc('created_at')->cursorPaginate(20);

        return $this->paginated($paginator, fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'author_request_status' => $user->author_request_status,
            'author_requested_at' => $user->author_requested_at,
            'created_at' => $user->created_at,
        ]);
    }

    /**
     * Grants author access outright - whether or not the user ever filed a
     * request. Covers both "approve their pending request" and "I want to
     * make this reader an author myself" from the same button.
     */
    public function grantAuthor(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if ($user->role === 'admin') {
            return $this->error('invalid_target', 'Admins already have full access.', 422);
        }

        $wasAlreadyAuthor = $user->role === 'author';

        $user->update(['role' => 'author', 'author_request_status' => 'none', 'author_requested_at' => null]);

        if (! $wasAlreadyAuthor) {
            $user->notify(new AuthorStatusChanged('granted'));
        }

        return $this->ok($this->userSummary($user->fresh()));
    }

    /** Declines a pending (or previously rejected) request without changing their role. */
    public function rejectAuthorRequest(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $user->update(['author_request_status' => 'rejected']);
        $user->notify(new AuthorStatusChanged('rejected'));

        return $this->ok($this->userSummary($user->fresh()));
    }

    /**
     * Moves an author back to reader. By default also unpublishes every
     * published story across all of their pen names - "revoke" is meant for
     * when an admin doesn't want that content live anymore, not just a
     * label change, but it can be skipped with unpublish_stories=false when
     * an admin just wants to pause someone's publishing rights and sort the
     * content out separately.
     */
    public function revokeAuthor(Request $request, int $id)
    {
        $data = $request->validate([
            'unpublish_stories' => ['sometimes', 'boolean'],
        ]);

        $user = User::findOrFail($id);

        if ($user->role !== 'author') {
            return $this->error('invalid_target', 'This user is not currently an author.', 422);
        }

        $penNameIds = $user->penNames()->pluck('id');
        $unpublishedCount = 0;

        if ($data['unpublish_stories'] ?? true) {
            $unpublishedCount = Story::whereIn('pen_name_id', $penNameIds)
                ->where('status', 'published')
                ->count();

            Story::whereIn('pen_name_id', $penNameIds)
                ->where('status', 'published')
                ->update(['status' => 'draft']);

            if ($unpublishedCount > 0) {
                \App\Support\HomeCache::forgetHomepage();
            }
        }

        $user->update(['role' => 'reader', 'author_request_status' => 'none', 'author_requested_at' => null]);
        $user->notify(new AuthorStatusChanged('revoked'));

        return $this->ok([...$this->userSummary($user->fresh()), 'unpublished_stories_count' => $unpublishedCount]);
    }

    private function userSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'author_request_status' => $user->author_request_status,
            'author_requested_at' => $user->author_requested_at,
        ];
    }
}

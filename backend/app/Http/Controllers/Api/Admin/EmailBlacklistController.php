<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlacklistedEmail;
use Illuminate\Http\Request;

/**
 * Manual mail-risk mitigation while we're on unauthenticated SMTP with no bounce
 * webhook: an admin marks an address that hard-bounced as blacklisted here, and
 * register()/login() in AuthController reject it up front with a clear message
 * instead of silently failing to deliver the OTP.
 *
 * Deliberately admin-gated rather than a fully public endpoint: an open,
 * unauthenticated "blacklist this email" endpoint would let anyone lock any
 * other person out of signing up, which is worse than the mail-risk problem
 * it's meant to solve.
 */
class EmailBlacklistController extends Controller
{
    public function index(Request $request)
    {
        return $this->ok(BlacklistedEmail::orderByDesc('created_at')->paginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $email = mb_strtolower($data['email']);

        $entry = BlacklistedEmail::updateOrCreate(
            ['email' => $email],
            ['reason' => $data['reason'] ?? null, 'blacklisted_by' => $this->requireUser($request)->id]
        );

        return $this->ok($entry, 201);
    }

    /**
     * GET convenience form of store() - lets you blacklist an address by just
     * hitting a URL (e.g. from a browser tab or a curl one-liner) with your
     * admin token, without needing to build a form for it. Same admin gate,
     * same effect as the POST endpoint above.
     */
    public function storeViaGet(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $email = mb_strtolower($data['email']);

        $entry = BlacklistedEmail::updateOrCreate(
            ['email' => $email],
            ['reason' => $data['reason'] ?? null, 'blacklisted_by' => $this->requireUser($request)->id]
        );

        return $this->ok($entry);
    }

    public function destroy(Request $request, string $email)
    {
        BlacklistedEmail::where('email', mb_strtolower($email))->delete();

        return $this->ok(['message' => 'Email removed from blacklist.']);
    }
}

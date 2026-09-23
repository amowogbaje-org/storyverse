<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

abstract class Controller
{
    protected function currentUser(Request $request): ?User
    {
        return $request->attributes->get('auth_user');
    }

    protected function requireUser(Request $request): User
    {
        return $this->currentUser($request)
            ?? abort(response()->json([
                'error' => ['code' => 'unauthenticated', 'message' => 'A valid token is required.'],
            ], 401));
    }

    protected function ok(mixed $data, int $status = 200)
    {
        return response()->json(['data' => $data], $status);
    }

    protected function paginated(CursorPaginator $paginator, ?callable $transform = null)
    {
        $items = $paginator->items();

        if ($transform) {
            $items = array_map($transform, $items);
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'cursor' => $paginator->nextCursor()?->encode(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    protected function error(string $code, string $message, int $status = 400, array $extra = [])
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message], ...$extra], $status);
    }

    /**
     * A stable-ish per-visitor identifier for anonymous analytics (site visits,
     * story views), sourced from a client-generated header when present so a
     * single guest session isn't double counted across multiple requests.
     */
    protected function sessionHash(Request $request): string
    {
        return substr((string) ($request->header('X-Session-Id') ?? $request->ip()), 0, 64);
    }
}

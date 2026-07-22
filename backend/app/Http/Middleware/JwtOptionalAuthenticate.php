<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;

/**
 * Optional auth: never blocks the request. Populates `auth_user` on the request
 * if a valid token is present, otherwise leaves it null (guest). Use on routes
 * marked "Optional" in the API design doc — e.g. story listing, where the
 * response shape (locked flags, is_liked_by_me) differs by auth state but a
 * guest should still get a 200, not a 401.
 */
class JwtOptionalAuthenticate
{
    public function __construct(private JwtService $jwt) {}

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if ($token) {
            $user = $this->jwt->userFromToken($token);
            $request->attributes->set('auth_user', $user);
        }

        return $next($request);
    }
}

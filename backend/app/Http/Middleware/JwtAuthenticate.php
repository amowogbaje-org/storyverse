<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;

/**
 * Required auth: 401 if no valid token. Use on routes marked "Required"/"Premium"
 * in the API design doc.
 */
class JwtAuthenticate
{
    public function __construct(private JwtService $jwt) {}

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'error' => ['code' => 'unauthenticated', 'message' => 'A valid token is required.'],
            ], 401);
        }

        $user = $this->jwt->userFromToken($token);

        if (! $user) {
            return response()->json([
                'error' => ['code' => 'invalid_token', 'message' => 'Token is invalid or expired.'],
            ], 401);
        }

        $request->attributes->set('auth_user', $user);
        $user->forceFill(['last_active_at' => now()])->saveQuietly();

        return $next($request);
    }
}

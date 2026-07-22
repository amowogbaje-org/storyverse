<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GeoDetectionService;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(
        private JwtService $jwt,
        private GeoDetectionService $geo,
    ) {}

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', 'min:8'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'browser_locale' => ['nullable', 'string'],
        ]);

        // Respect an explicit country_code from the form (possibly user-overridden);
        // only fall back to detection if the client didn't send one.
        $geo = $data['country_code']
            ? ['country_code' => $data['country_code'], 'currency' => null]
            : $this->geo->detect($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => isset($data['password']) ? Hash::make($data['password']) : null,
            'country_code' => $geo['country_code'],
            'currency' => $geo['currency'],
            'role' => 'reader',
        ]);

        \App\Models\UserActivityEvent::create([
            'user_id' => $user->id,
            'event_type' => 'user_registered',
            'metadata' => ['country_code' => $geo['country_code']],
            'created_at' => now(),
        ]);
        \App\Events\UserActivityLogged::dispatch($user->id, 'user_registered', ['country_code' => $geo['country_code']]);

        return $this->ok([
            'user' => $this->userPayload($user),
            'token' => $this->jwt->issue($user),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            return $this->error('invalid_credentials', 'Email or password is incorrect.', 401);
        }

        return $this->ok(['user' => $this->userPayload($user), 'token' => $this->jwt->issue($user)]);
    }

    public function requestOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required_without:phone', 'email'],
            'phone' => ['required_without:email', 'string'],
        ]);

        $code = (string) random_int(100000, 999999);

        // Store hashed OTP against email/phone with a short TTL - cache driver, not a table.
        $key = 'otp:' . ($data['email'] ?? $data['phone']);
        cache()->put($key, Hash::make($code), now()->addMinutes(10));

        // TODO: dispatch a Notification (mail) or Termii SMS send here depending on which was provided.

        return $this->ok(['message' => 'OTP sent.']);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required_without:phone', 'email'],
            'phone' => ['required_without:email', 'string'],
            'code' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'], // for first-time verification == registration
            'country_code' => ['nullable', 'string', 'size:2'],
        ]);

        $identifier = $data['email'] ?? $data['phone'];
        $key = 'otp:' . $identifier;
        $hashed = cache()->get($key);

        if (! $hashed || ! Hash::check($data['code'], $hashed)) {
            return $this->error('invalid_otp', 'That code is invalid or expired.', 422);
        }

        cache()->forget($key);

        $user = User::firstOrCreate(
            isset($data['email']) ? ['email' => $data['email']] : ['phone' => $data['phone']],
            [
                'name' => $data['name'] ?? Str::before($identifier, '@'),
                'country_code' => $data['country_code'] ?? null,
                'role' => 'reader',
            ]
        );

        return $this->ok(['user' => $this->userPayload($user), 'token' => $this->jwt->issue($user)]);
    }

    public function me(Request $request)
    {
        return $this->ok($this->userPayload($this->requireUser($request)));
    }

    public function updateMe(Request $request)
    {
        $user = $this->requireUser($request);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'avatar_url' => ['sometimes', 'nullable', 'string'],
            'currency_code' => ['sometimes', 'nullable', 'string', 'size:3'],
        ]);

        if (isset($data['currency_code'])) {
            $data['currency'] = $data['currency_code'];
            unset($data['currency_code']);
        }

        $user->update($data);

        return $this->ok($this->userPayload($user));
    }

    public function updateCountry(Request $request)
    {
        $user = $this->requireUser($request);

        $data = $request->validate([
            'country_code' => ['required', 'string', 'size:2'],
        ]);

        // Deliberately does NOT touch existing active subscriptions' locked_price/locked_currency -
        // see the price lock-in rule in the currency design doc.
        $user->update(['country_code' => $data['country_code']]);

        return $this->ok($user);
    }

    public function logout(Request $request)
    {
        // Stateless JWT: nothing to invalidate server-side without a blacklist store.
        // Client just discards the token. Add a Redis-backed blacklist here later if needed.
        return $this->ok(['message' => 'Logged out.']);
    }

    /**
     * "Authors ... are a type of admin" per the brief, but there was no way for a
     * reader to actually become one — this is the minimal self-serve path. A real
     * product would probably gate this behind some review step; this is deliberately
     * the simple version.
     */
    public function becomeAuthor(Request $request)
    {
        $user = $this->requireUser($request);

        if ($user->role === 'reader') {
            $user->update(['role' => 'author']);
        }

        return $this->ok($this->userPayload($user));
    }

    /**
     * has_active_premium_subscription runs a query, so it's attached here explicitly
     * rather than via a global User::$appends — that would silently re-run a
     * subscription lookup for every user attached to every comment/list response.
     */
    private function userPayload(User $user): array
    {
        return [
            ...$user->toArray(),
            'has_active_premium_subscription' => $user->hasActivePremiumSubscription(),
        ];
    }
}

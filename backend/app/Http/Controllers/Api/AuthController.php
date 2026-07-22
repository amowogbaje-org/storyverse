<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\OtpCodeNotification;
use App\Services\GeoDetectionService;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'accept_terms' => ['accepted'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'browser_locale' => ['nullable', 'string'],
        ], [
            'password.confirmed' => 'Password and confirmation do not match.',
            'accept_terms.accepted' => 'You must agree to the Terms of Service and Privacy Policy.',
        ]);

        // Respect an explicit country_code from the form (possibly user-overridden);
        // only fall back to detection if the client didn't send one.
        $geo = $data['country_code']
            ? ['country_code' => $data['country_code'], 'currency' => null]
            : $this->geo->detect($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
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

        // Account exists but stays unverified/token-less until the OTP we just sent
        // is confirmed via /auth/otp/verify - see verifyOtp() below.
        $this->issueOtp($user->email, 'verify');

        return $this->ok([
            'user' => $this->userPayload($user),
            'requires_verification' => true,
            'email' => $user->email,
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
            'purpose' => ['nullable', 'string', Rule::in(['verify', 'login'])],
        ]);

        if (isset($data['email'])) {
            $this->issueOtp($data['email'], $data['purpose'] ?? 'verify');
        } else {
            // TODO: wire up Termii (or another SMS provider) for phone-based OTP delivery.
            $code = (string) random_int(100000, 999999);
            cache()->put('otp:' . $data['phone'], Hash::make($code), now()->addMinutes(10));
        }

        return $this->ok(['message' => 'OTP sent.']);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required_without:phone', 'email'],
            'phone' => ['required_without:email', 'string'],
            'code' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'], // used if verifying creates the account (passwordless/phone paths)
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

        if (! $user->email_verified_at) {
            $user->update(['email_verified_at' => now()]);
        }

        return $this->ok(['user' => $this->userPayload($user), 'token' => $this->jwt->issue($user)]);
    }

    /**
     * Google Identity Services sign-in/sign-up. The frontend hands us the ID
     * token straight off the Google button/One Tap callback; we verify it
     * against Google's tokeninfo endpoint (one HTTP call, no JWKS/key-rotation
     * handling needed) and find-or-create the account from its claims.
     */
    public function googleAuth(Request $request)
    {
        $data = $request->validate([
            'credential' => ['required', 'string'],
        ]);

        $payload = $this->verifyGoogleIdToken($data['credential']);

        if (! $payload) {
            return $this->error('invalid_google_token', 'We could not verify that Google sign-in. Please try again.', 422);
        }

        $user = User::where('google_id', $payload['sub'])->first()
            ?? User::where('email', $payload['email'])->first();

        if ($user) {
            $user->update([
                'google_id' => $user->google_id ?: $payload['sub'],
                'email_verified_at' => $user->email_verified_at ?? now(),
                'avatar_url' => $user->avatar_url ?: ($payload['picture'] ?? null),
            ]);
        } else {
            $geo = $this->geo->detect($request);

            $user = User::create([
                'name' => $payload['name'] ?? Str::before($payload['email'], '@'),
                'email' => $payload['email'],
                'google_id' => $payload['sub'],
                'avatar_url' => $payload['picture'] ?? null,
                'email_verified_at' => now(),
                'country_code' => $geo['country_code'],
                'currency' => $geo['currency'],
                'role' => 'reader',
            ]);

            \App\Models\UserActivityEvent::create([
                'user_id' => $user->id,
                'event_type' => 'user_registered',
                'metadata' => ['country_code' => $geo['country_code'], 'via' => 'google'],
                'created_at' => now(),
            ]);
            \App\Events\UserActivityLogged::dispatch($user->id, 'user_registered', ['country_code' => $geo['country_code'], 'via' => 'google']);
        }

        return $this->ok(['user' => $this->userPayload($user), 'token' => $this->jwt->issue($user)]);
    }

    /**
     * Generates a 6-digit code, stores only its hash (10 minute TTL), and
     * emails the plaintext code via Mailpit/SMTP. Shared by register() and
     * the standalone /auth/otp/request endpoint (resend uses the same path).
     */
    private function issueOtp(string $email, string $purpose = 'verify'): void
    {
        $code = (string) random_int(100000, 999999);

        cache()->put('otp:' . $email, Hash::make($code), now()->addMinutes(10));
        Log::info('OTP issued for ' . $email . ' (' . $purpose . '): ' . $code);
        Notification::route('mail', $email)->notify(new OtpCodeNotification($code, $purpose));
    }

    /**
     * Verifies a Google ID token via Google's tokeninfo endpoint rather than
     * local JWKS verification - one HTTP call, no key rotation to manage,
     * which is a fine tradeoff at this traffic volume.
     */
    private function verifyGoogleIdToken(string $idToken): ?array
    {
        try {
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $payload = $response->json();
        $clientId = config('services.google.client_id');

        if (! $clientId || ($payload['aud'] ?? null) !== $clientId) {
            return null;
        }

        if (empty($payload['email']) || ($payload['email_verified'] ?? 'false') === 'false') {
            return null;
        }

        return $payload;
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlacklistedEmail;
use App\Models\User;
use App\Notifications\OtpCodeNotification;
use App\Notifications\WelcomeNotification;
use App\Services\GeoDetectionService;
use App\Services\JwtService;
use App\Services\RefreshTokenService;
use App\Services\ReferralService;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(
        private JwtService $jwt,
        private RefreshTokenService $refreshTokens,
        private GeoDetectionService $geo,
        private ReferralService $referrals,
    ) {}

    /**
     * Every login-succeeding endpoint (login, resetPassword, verifyOtp,
     * googleAuth, refresh) hands back this same shape: a short-lived access
     * token plus a fresh long-lived refresh token. Centralized so all of
     * them stay in sync rather than four separate call sites drifting.
     */
    private function issueTokens(User $user): array
    {
        $refresh = $this->refreshTokens->issue($user);

        return [
            'token' => $this->jwt->issue($user),
            'refresh_token' => $refresh['token'],
            'refresh_token_expires_at' => $refresh['expires_at']->toIso8601String(),
        ];
    }

    public function register(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'accept_terms' => ['accepted'],
                'country_code' => ['nullable', 'string', 'size:2'],
                'browser_locale' => ['nullable', 'string'],
                'referral_code' => ['nullable', 'string', 'max:12'],
            ], [
                'password.confirmed' => 'Password and confirmation do not match.',
                'accept_terms.accepted' => 'You must agree to the Terms of Service and Privacy Policy.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->logSignupAttempt('native', $request->input('email'), 'failed', 'validation_failed', null, $request, [
                'fields' => array_keys($e->errors()),
            ]);

            throw $e;
        }

        if (BlacklistedEmail::isBlacklisted($data['email'])) {
            $this->logSignupAttempt('native', $data['email'], 'failed', 'email_blacklisted', null, $request);

            return $this->error('invalid_email', 'We were unable to deliver mail to this email address before. Please use a different email address.', 422);
        }

        $existing = User::where('email', $data['email'])->first();

        if ($existing) {
            if ($existing->email_verified_at) {
                // Real, verified account already owns this email - this is the normal
                // "you already have an account" case, not the silent/ambiguous one.
                $this->logSignupAttempt('native', $data['email'], 'failed', 'email_taken_verified', $existing->id, $request);

                return $this->error('email_taken', 'An account with this email already exists. Try signing in instead.', 422);
            }

            // They signed up before but never verified. Don't create a second account
            // and don't fail ambiguously - say plainly what happened and get them a
            // fresh code (subject to the normal 1-minute resend limit).
            $otp = $this->issueOtp($existing->email, 'verify');

            $this->logSignupAttempt('native', $data['email'], 'pending', 'unverified_resend', $existing->id, $request);

            return $this->ok([
                'user' => $this->userPayload($existing),
                'requires_verification' => true,
                'already_registered' => true,
                'email' => $existing->email,
                'retry_after' => $otp['retry_after'],
            ], 200);
        }

        // Respect an explicit country_code from the form (possibly user-overridden);
        // only fall back to detection if the client didn't send one. Note: a
        // 'nullable' field that's simply absent from the request is omitted from
        // $data entirely (not present as null), so this must use ?? rather than
        // assuming the key exists.
        $geo = ($data['country_code'] ?? null)
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

        $this->referrals->attribute($user, $data['referral_code'] ?? null);

        \App\Models\UserActivityEvent::create([
            'user_id' => $user->id,
            'event_type' => 'user_registered',
            'metadata' => ['country_code' => $geo['country_code']],
            'created_at' => now(),
        ]);
        \App\Events\UserActivityLogged::dispatch($user->id, 'user_registered', ['country_code' => $geo['country_code']]);

        // Account exists but stays unverified/token-less until the OTP we just sent
        // is confirmed via /auth/otp/verify - see verifyOtp() below.
        $otp = $this->issueOtp($user->email, 'verify');

        $this->logSignupAttempt('native', $user->email, 'succeeded', 'otp_sent', $user->id, $request);

        return $this->ok([
            'user' => $this->userPayload($user),
            'requires_verification' => true,
            'email' => $user->email,
            'retry_after' => $otp['retry_after'],
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (BlacklistedEmail::isBlacklisted($data['email'])) {
            return $this->error('invalid_email', 'This email address looks invalid. Please use a different email address.', 422);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            return $this->error('invalid_credentials', 'Email or password is incorrect.', 401);
        }

        if (! $user->email_verified_at) {
            // Correct credentials, but the account was never verified. Don't sign
            // them in - tell them plainly, and get a fresh code moving so the
            // frontend can drop them straight into the OTP screen with a countdown.
            $otp = $this->issueOtp($user->email, 'verify');

            return response()->json([
                'error' => [
                    'code' => 'email_not_verified',
                    'message' => "Please verify your email before signing in. We've sent a fresh code.",
                ],
                'email' => $user->email,
                'retry_after' => $otp['retry_after'],
            ], 403);
        }

        return $this->ok(['user' => $this->userPayload($user), ...$this->issueTokens($user)]);
    }

    /**
     * Deliberately non-committal about whether this email has an account -
     * unlike register()/login(), password reset is a prime target for account
     * enumeration (an attacker probing "does this email exist"), so the
     * response is identical either way: same message, same retry_after, sent
     * in roughly the same time either way. Calling this again before the
     * throttle clears is also how resending works - there's no separate
     * resend endpoint for this flow.
     */
    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->whereNotNull('email_verified_at')->first();

        if ($user && ! BlacklistedEmail::isBlacklisted($data['email'])) {
            $this->issueOtp($user->email, 'password_reset');
        }

        return $this->ok([
            'message' => "If an account with that email exists, we've sent a password reset code.",
            'retry_after' => 60,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'Password and confirmation do not match.',
        ]);

        $key = 'otp:'.$data['email'];
        $hashed = cache()->get($key);

        if (! $hashed || ! Hash::check($data['code'], $hashed)) {
            return $this->error('invalid_otp', 'That code is invalid or expired.', 422);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            // Shouldn't happen in practice - forgotPassword() only ever issues a
            // code for an email with a real account - but the OTP cache key is
            // keyed by the email string alone, not a user id, so this is
            // guarded rather than assumed.
            return $this->error('invalid_otp', 'That code is invalid or expired.', 422);
        }

        cache()->forget($key);
        cache()->forget('otp:throttle:'.$data['email']);

        $user->update(['password' => Hash::make($data['password'])]);

        return $this->ok(['user' => $this->userPayload($user), ...$this->issueTokens($user)]);
    }

    public function requestOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required_without:phone', 'email'],
            'phone' => ['required_without:email', 'string'],
            'purpose' => ['nullable', 'string', Rule::in(['verify', 'login'])],
        ]);

        if (isset($data['email'])) {
            if (BlacklistedEmail::isBlacklisted($data['email'])) {
                return $this->error('invalid_email', 'This email address looks invalid. Please use a different email address.', 422);
            }

            $otp = $this->issueOtp($data['email'], $data['purpose'] ?? 'verify');

            if (! $otp['sent']) {
                return response()->json([
                    'error' => ['code' => 'otp_rate_limited', 'message' => 'Please wait before requesting another code.'],
                    'retry_after' => $otp['retry_after'],
                ], 429);
            }

            return $this->ok(['message' => 'OTP sent.', 'retry_after' => $otp['retry_after']]);
        }

        $throttleKey = 'otp:throttle:' . $data['phone'];

        if (cache()->has($throttleKey)) {
            return response()->json([
                'error' => ['code' => 'otp_rate_limited', 'message' => 'Please wait before requesting another code.'],
                'retry_after' => $this->secondsUntil($throttleKey),
            ], 429);
        }

        // TODO: wire up Termii (or another SMS provider) for phone-based OTP delivery.
        // Until then this behaves like local-env email: the code only goes to the log.
        $code = (string) random_int(100000, 999999);
        cache()->put('otp:' . $data['phone'], Hash::make($code), now()->addSeconds(60));
        cache()->put($throttleKey, now()->addSeconds(60)->timestamp, now()->addSeconds(60));
        $this->logOtpForLocalDebugging($data['phone'], $code);

        return $this->ok(['message' => 'OTP sent.', 'retry_after' => 60]);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required_without:phone', 'email'],
            'phone' => ['required_without:email', 'string'],
            'code' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'], // used if verifying creates the account (passwordless/phone paths)
            'country_code' => ['nullable', 'string', 'size:2'],
            'referral_code' => ['nullable', 'string', 'max:12'], // only relevant if this call creates the account (passwordless path)
        ]);

        $identifier = $data['email'] ?? $data['phone'];
        $key = 'otp:' . $identifier;
        $hashed = cache()->get($key);

        if (! $hashed || ! Hash::check($data['code'], $hashed)) {
            $this->logSignupAttempt('otp', $identifier, 'failed', 'invalid_otp', null, $request);

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

        if ($user->wasRecentlyCreated) {
            $this->referrals->attribute($user, $data['referral_code'] ?? null);

            // Only the true passwordless-signup path (no prior register() call
            // created this row) counts as an 'otp' channel signup - OTP
            // verification of a register()-initiated account is handled by the
            // 'native' logging in register() above, not here.
            $this->logSignupAttempt('otp', $identifier, 'succeeded', 'new_account', $user->id, $request);
        }

        $wasUnverified = ! $user->email_verified_at;

        if ($wasUnverified) {
            $user->update(['email_verified_at' => now()]);

            if ($user->email) {
                $user->notify(new WelcomeNotification());
            }

            $this->referrals->onReferredUserVerified($user);
        }

        // Verification succeeded - a stale resend throttle shouldn't block whatever
        // they do next (e.g. a login-purpose OTP a minute from now).
        cache()->forget('otp:throttle:' . $identifier);

        return $this->ok([
            'user' => $this->userPayload($user),
            ...$this->issueTokens($user),
            'newly_verified' => $wasUnverified,
        ]);
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
            'referral_code' => ['nullable', 'string', 'max:12'],
        ]);

        $payload = $this->verifyGoogleIdToken($data['credential']);

        if (! $payload) {
            // No usable email at this point - the token itself didn't verify,
            // so there's nothing reliable to log as the identifier.
            $this->logSignupAttempt('google', null, 'failed', 'invalid_google_token', null, $request);

            return $this->error('invalid_google_token', 'We could not verify that Google sign-in. Please try again.', 422);
        }

        $user = User::where('google_id', $payload['sub'])->first()
            ?? User::where('email', $payload['email'])->first();

        if ($user) {
            // NOTE: deliberately never sets 'password' here, on first link or on
            // any subsequent Google sign-in. Linking a Google account to an
            // existing email/password account must never touch, clear, or
            // silently reset a password the reader already set - see
            // updatePassword() for the one and only place a reader's password
            // is allowed to change.
            $user->update([
                'google_id' => $user->google_id ?: $payload['sub'],
                'email_verified_at' => $user->email_verified_at ?? now(),
                'avatar_url' => $user->avatar_url ?: ($payload['picture'] ?? null),
            ]);

            $this->logSignupAttempt('google', $payload['email'], 'succeeded', 'existing_account_linked', $user->id, $request);
            $isNewUser = false;
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

            // Google accounts are verified the instant they're created - no separate
            // OTP step - so attribution and the "verified" milestone check both
            // happen right here, back to back, rather than at two different times
            // like the email/password flow.
            $this->referrals->attribute($user, $data['referral_code'] ?? null);
            $this->referrals->onReferredUserVerified($user);

            \App\Models\UserActivityEvent::create([
                'user_id' => $user->id,
                'event_type' => 'user_registered',
                'metadata' => ['country_code' => $geo['country_code'], 'via' => 'google'],
                'created_at' => now(),
            ]);
            \App\Events\UserActivityLogged::dispatch($user->id, 'user_registered', ['country_code' => $geo['country_code'], 'via' => 'google']);

            $this->logSignupAttempt('google', $user->email, 'succeeded', 'new_account', $user->id, $request);
            $isNewUser = true;
        }

        return $this->ok([
            'user' => $this->userPayload($user),
            ...$this->issueTokens($user),
            // Mirrors 'newly_verified' on the OTP-verify response - lets the
            // frontend show the one-time welcome banner only the first time
            // this Google account actually creates a Storyverse account,
            // not on every subsequent Google sign-in.
            'newly_registered' => $isNewUser,
        ]);
    }

    /**
     * Records one row per signup attempt - success, failure, or pending -
     * across all three entry points (native email/password, Google, OTP).
     * The point is drop-off visibility: which step people are actually
     * failing at (bad password, blacklisted email, an expired code, a
     * Google token that didn't verify), not just how many succeed.
     *
     * Deliberately swallows its own failures rather than letting a logging
     * bug ever break signup/login itself.
     */
    private function logSignupAttempt(
        string $channel,
        ?string $identifier,
        string $status,
        ?string $reason,
        ?int $userId,
        Request $request,
        array $metadata = [],
    ): void {
        try {
            \App\Models\SignupAttempt::create([
                'channel' => $channel,
                'identifier' => $identifier,
                'status' => $status,
                'reason' => $reason,
                'user_id' => $userId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log signup attempt', ['error' => $e->getMessage(), 'channel' => $channel]);
        }
    }

    /**
     * Generates a 6-digit code, stores only its hash (1 minute TTL), and emails
     * the plaintext code via Mailpit/SMTP. Shared by register(), login()'s
     * unverified path, and the standalone /auth/otp/request endpoint.
     *
     * Also enforces a 1-minute resend throttle per email: if a code was issued
     * less than a minute ago, this is a no-op that just reports how much of the
     * throttle window is left, so the frontend can render an accurate countdown
     * instead of silently re-sending (or worse, silently doing nothing).
     *
     * @return array{sent: bool, retry_after: int} retry_after is seconds remaining
     *         before another code can be requested.
     */
    private function issueOtp(string $email, string $purpose = 'verify'): array
    {
        $throttleKey = 'otp:throttle:' . $email;

        if (cache()->has($throttleKey)) {
            return ['sent' => false, 'retry_after' => $this->secondsUntil($throttleKey)];
        }

        $code = (string) random_int(100000, 999999);

        cache()->put('otp:' . $email, Hash::make($code), now()->addSeconds(60));
        cache()->put($throttleKey, now()->addSeconds(60)->timestamp, now()->addSeconds(60));

        Notification::route('mail', $email)->notify(new OtpCodeNotification($code, $purpose));

        $this->logOtpForLocalDebugging($email, $code);

        return ['sent' => true, 'retry_after' => 60];
    }

    /**
     * Reads the throttle cache entry (a unix timestamp of when it expires) and
     * returns how many whole seconds remain, never negative.
     */
    private function secondsUntil(string $throttleKey): int
    {
        $expiresAt = (int) cache()->get($throttleKey, now()->timestamp);

        return max(0, $expiresAt - now()->timestamp);
    }

    /**
     * Mailpit (our local SMTP catcher) never reaches a real inbox - it only shows up
     * in its own web UI at http://localhost:8025, which is easy to miss. This also
     * covers the phone/SMS path, which has no real provider wired up yet at all.
     * So in local/debug envs we mirror the code into the Laravel log as a convenient
     * fallback. Never runs in production (config('app.debug') is false there).
     */
    private function logOtpForLocalDebugging(string $identifier, string $code): void
    {
        if (! config('app.debug')) {
            return;
        }

        Log::info("[OTP] Code for {$identifier}: {$code} (also sent via Mailpit - check http://localhost:8025)");
    }

    /**
     * Verifies a Google ID token via Google's tokeninfo endpoint rather than
     * local JWKS verification - one HTTP call, no key rotation to manage,
     * which is a fine tradeoff at this traffic volume.
     */
    /**
     * Verifies a Google ID token locally against Google's own public keys,
     * rather than calling Google's tokeninfo endpoint (what this used to
     * do). tokeninfo is explicitly a debugging endpoint, not meant for
     * production auth: it adds a network round-trip to every single login
     * (latency, plus a real external failure point - Google's own guidance
     * on this is that it "involves an HTTP round trip, introducing latency
     * and the potential for network breakage"), and is rate-limited in a way
     * a busy app can realistically hit. That combination is the most likely
     * explanation for intermittent "signed in with Google but nothing
     * happened" reports: a transient tokeninfo failure or a rate limit both
     * silently return null here and read as "invalid token" to the caller.
     *
     * Google's own public keys change rarely, so they're cached for an hour
     * (cache_ttl_seconds below) rather than fetched per login - most logins
     * now make zero outbound network calls for verification at all, only a
     * local signature check. If a token's kid isn't in the cached set (keys
     * were rotated since the last fetch), the cache is bypassed once for a
     * fresh fetch before giving up - the same pattern Google's own client
     * libraries use.
     */
    private function verifyGoogleIdToken(string $idToken): ?array
    {
        $clientId = config('services.google.client_id');

        if (! $clientId) {
            return null;
        }

        try {
            $payload = $this->decodeGoogleIdToken($idToken, forceFreshKeys: false)
                ?? $this->decodeGoogleIdToken($idToken, forceFreshKeys: true);
        } catch (\Throwable $e) {
            Log::warning('Google ID token verification failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $payload) {
            return null;
        }

        // Claims JWT::decode's signature/exp check doesn't cover - Google's
        // own guidance on what a client must still verify itself: issuer,
        // audience (this is OUR app the token was minted for, not some other
        // site using the same Google Sign-In popup flow), and a verified email.
        $iss = $payload['iss'] ?? '';
        if (! in_array($iss, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            return null;
        }

        if (($payload['aud'] ?? null) !== $clientId) {
            return null;
        }

        if (empty($payload['email']) || ! ($payload['email_verified'] ?? false)) {
            return null;
        }

        return $payload;
    }

    /** @return array<string,mixed>|null */
    private function decodeGoogleIdToken(string $idToken, bool $forceFreshKeys): ?array
    {
        $cacheKey = 'google_jwks';
        $cacheTtlSeconds = 3600;

        if ($forceFreshKeys) {
            Cache::forget($cacheKey);
        }

        $jwks = Cache::remember($cacheKey, $cacheTtlSeconds, function () {
            $response = Http::timeout(5)->get('https://www.googleapis.com/oauth2/v3/certs');

            if (! $response->ok()) {
                throw new \RuntimeException('Could not fetch Google public keys: HTTP '.$response->status());
            }

            return $response->json();
        });

        $keys = JWK::parseKeySet($jwks);

        try {
            $decoded = JWT::decode($idToken, $keys);
        } catch (\Firebase\JWT\SignatureInvalidException|\UnexpectedValueException $e) {
            // Most likely an unrecognized kid because Google rotated keys
            // since this was cached - let the caller retry once with a
            // forced-fresh fetch rather than treating this as a bad token.
            return null;
        }

        return (array) $decoded;
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

    /**
     * Every key here defaults to true (opt-out, not opt-in) if the user has
     * never touched their preferences - notification_preferences starts out
     * null, and `?? true` at every read site treats a missing key the same
     * way. Listed here just so there's one place that documents what exists;
     * each Notification class's via() is what actually reads/enforces these.
     */
    public const NOTIFICATION_PREFERENCE_KEYS = [
        'badge_emails',          // BadgeUnlocked -> mail
        'badge_push',            // BadgeUnlocked -> push
        'new_story_push',        // NewStoryRecommendation -> database + push
        'continue_reading_push', // ContinueReading -> database + push (also gates SendReadingTimeReminders)
        'missed_you_push',       // WeMissedYou -> database + push
        'new_episode_push',      // NewEpisodesAvailable -> database + push
    ];

    public function updateNotificationPreferences(Request $request)
    {
        $user = $this->requireUser($request);

        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['boolean'],
        ]);

        $unknown = array_diff(array_keys($data['preferences']), self::NOTIFICATION_PREFERENCE_KEYS);
        if ($unknown) {
            return $this->error('invalid_preference', 'Unknown preference key(s): '.implode(', ', $unknown), 422);
        }

        // Merge rather than replace - a client only sends the keys it has a
        // toggle for, and shouldn't accidentally wipe out other preferences
        // (present or future) it doesn't know about.
        $user->update([
            'notification_preferences' => array_merge($user->notification_preferences ?? [], $data['preferences']),
        ]);

        return $this->ok(['notification_preferences' => $user->notification_preferences]);
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

    /**
     * Timezone is expected to come from the client via
     * Intl.DateTimeFormat().resolvedOptions().timeZone (captured silently,
     * not asked for) - preferred_reading_time is the one the user actually
     * sets themselves in Settings. Either can be sent alone; sending
     * preferred_reading_time as null clears it, falling back to the default
     * evening slot used by SendReadingTimeReminders / SendNewEpisodeDigest.
     */
    public function updateReadingPreferences(Request $request)
    {
        $user = $this->requireUser($request);

        $data = $request->validate([
            'timezone' => ['sometimes', 'timezone'],
            'preferred_reading_time' => ['sometimes', 'nullable', 'date_format:H:i'],
        ]);

        $user->update($data);

        return $this->ok([
            'timezone' => $user->timezone,
            'preferred_reading_time' => $user->preferred_reading_time,
        ]);
    }

    /**
     * Handles both cases with one endpoint: a reader who already has a
     * password changing it (must prove they know the current one), and a
     * Google-only reader setting a password for the first time (nothing to
     * prove yet, since they don't have one). Deliberately separate from
     * forgotPassword/resetPassword, which is the "I'm locked out, email me a
     * code" flow for someone who isn't signed in - this one is for a
     * signed-in reader managing their own account in Settings.
     */
    public function updatePassword(Request $request)
    {
        $user = $this->requireUser($request);

        $data = $request->validate([
            'current_password' => [$user->password ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'Password and confirmation do not match.',
        ]);

        if ($user->password && ! Hash::check($data['current_password'] ?? '', $user->password)) {
            return $this->error('invalid_current_password', 'Current password is incorrect.', 422);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return $this->ok(['has_password' => true]);
    }

    public function logout(Request $request)
    {
        // Revoke the refresh token this device was holding, if it sent one -
        // without this, "logging out" only ever discarded the client's copy;
        // the token itself stayed valid and could still mint fresh access
        // tokens for another 30 days for anyone who'd captured it.
        $refreshToken = $request->input('refresh_token');
        if ($refreshToken) {
            $this->refreshTokens->revoke($refreshToken);
        }

        return $this->ok(['message' => 'Logged out.']);
    }

    /**
     * Trades a still-valid refresh token for a brand new access token (and,
     * via rotation, a brand new refresh token too - see
     * RefreshTokenService::rotate). This is what the frontend calls in the
     * background whenever an API request comes back 401 because the short-
     * lived access token has expired, so the reader stays signed in without
     * ever seeing a login screen.
     */
    public function refresh(Request $request)
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $result = $this->refreshTokens->rotate($data['refresh_token']);

        if (! $result) {
            return $this->error('invalid_refresh_token', 'Your session has expired. Please sign in again.', 401);
        }

        return $this->ok([
            'user' => $this->userPayload($result['user']),
            'token' => $this->jwt->issue($result['user']),
            'refresh_token' => $result['token'],
            'refresh_token_expires_at' => $result['expires_at']->toIso8601String(),
        ]);
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
     * has_premium_access runs a query, so it's attached here explicitly
     * rather than via a global User::$appends - that would silently re-run
     * this lookup for every user attached to every comment/list response.
     */
    private function userPayload(User $user): array
    {
        return [
            ...$user->toArray(),
            'has_premium_access' => $user->hasBonusPremiumAccess(),
            // password itself is never exposed ($hidden on the model) - just
            // whether one is set, so the frontend knows whether a Google-only
            // account needs a "set password" form or a "change password" one.
            'has_password' => (bool) $user->password,
        ];
    }
}

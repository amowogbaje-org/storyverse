<?php

namespace App\Http\Controllers;

use App\Models\BlacklistedEmail;
use App\Models\User;
use App\Notifications\OtpCodeNotification;
use App\Notifications\WelcomeNotification;
use App\Services\GeoDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Session-based now (Auth::login()), not JWT - this app has real cookies, so
 * the token/refresh-token dance the old JWT-only backend needed doesn't
 * apply here. Referral attribution and signup-attempt analytics from the
 * old AuthController were dropped for this first pass (acquisition-funnel
 * tooling, not core to reading/publishing) - straightforward to add back
 * later against ReferralService/SignupAttempt, both already ported.
 */
class AuthController extends Controller
{
    public function __construct(private GeoDetectionService $geo) {}

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'accept_terms' => ['accepted'],
        ], [
            'password.confirmed' => 'Password and confirmation do not match.',
            'accept_terms.accepted' => 'You must agree to the Terms of Service and Privacy Policy.',
        ]);

        if (BlacklistedEmail::isBlacklisted($data['email'])) {
            return back()->withErrors(['email' => 'We were unable to deliver mail to this address before. Please use a different email.'])->withInput();
        }

        $existing = User::where('email', $data['email'])->first();

        if ($existing && $existing->email_verified_at) {
            return back()->withErrors(['email' => 'An account with this email already exists. Try signing in instead.'])->withInput();
        }

        if ($existing) {
            // Signed up before, never verified - resend rather than duplicate.
            $this->issueOtp($existing->email, 'verify');

            return redirect()->route('auth.verify.show', ['email' => $existing->email]);
        }

        $geo = $this->geo->detect($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'country_code' => $geo['country_code'],
            'currency' => $geo['currency'],
            'role' => 'reader',
        ]);

        $this->issueOtp($user->email, 'verify');

        return redirect()->route('auth.verify.show', ['email' => $user->email]);
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['email' => 'Email or password is incorrect.'])->onlyInput('email');
        }

        if (! $user->email_verified_at) {
            $this->issueOtp($user->email, 'verify');

            return redirect()->route('auth.verify.show', ['email' => $user->email])
                ->with('status', 'Please verify your email first - we just sent you a fresh code.');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showVerify(Request $request)
    {
        return view('auth.verify', ['email' => $request->query('email')]);
    }

    public function resendOtp(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $data['email'])->first();

        if ($user && ! $user->email_verified_at) {
            $result = $this->issueOtp($user->email, 'verify');

            if (! $result['sent']) {
                return back()->with('status', 'A code was already sent recently - check your inbox, or wait a moment before requesting another.');
            }
        }

        return back()->with('status', 'A new code has been sent.');
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
        ]);

        $key = 'otp:'.$data['email'];
        $hashed = cache()->get($key);

        if (! $hashed || ! Hash::check($data['code'], $hashed)) {
            return back()->withErrors(['code' => 'That code is invalid or expired.'])->withInput();
        }

        cache()->forget($key);

        $user = User::where('email', $data['email'])->firstOrFail();
        $wasUnverified = ! $user->email_verified_at;

        if ($wasUnverified) {
            $user->update(['email_verified_at' => now()]);
            $user->notify(new WelcomeNotification());
        }

        cache()->forget('otp:throttle:'.$data['email']);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $data['email'])->whereNotNull('email_verified_at')->first();

        if ($user && ! BlacklistedEmail::isBlacklisted($data['email'])) {
            $this->issueOtp($user->email, 'password_reset');
        }

        return redirect()->route('auth.reset-password.show', ['email' => $data['email']])
            ->with('status', "If an account with that email exists, we've sent a password reset code.");
    }

    public function showResetPassword(Request $request)
    {
        return view('auth.reset-password', ['email' => $request->query('email')]);
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
            return back()->withErrors(['code' => 'That code is invalid or expired.'])->withInput();
        }

        $user = User::where('email', $data['email'])->firstOrFail();
        $user->update(['password' => Hash::make($data['password'])]);
        cache()->forget($key);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('status', 'Your password has been reset.');
    }

    /** @return array{sent: bool, retry_after: int} */
    private function issueOtp(string $email, string $purpose = 'verify'): array
    {
        $throttleKey = 'otp:throttle:'.$email;

        if (cache()->has($throttleKey)) {
            return ['sent' => false, 'retry_after' => cache()->get($throttleKey) - now()->timestamp];
        }

        $code = (string) random_int(100000, 999999);

        cache()->put('otp:'.$email, Hash::make($code), now()->addMinutes(10));
        cache()->put($throttleKey, now()->addSeconds(60)->timestamp, now()->addSeconds(60));

        \Illuminate\Support\Facades\Notification::route('mail', $email)->notify(new OtpCodeNotification($code, $purpose));

        return ['sent' => true, 'retry_after' => 60];
    }
}

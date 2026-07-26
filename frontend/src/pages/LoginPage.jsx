import { useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import Container from "../components/common/Container";
import PasswordInput from "../components/auth/PasswordInput";
import GoogleButton from "../components/auth/GoogleButton";
import OtpForm from "../components/auth/OtpForm";

export default function LoginPage() {
  const { login, verifyOtp, resendOtp } = useAuth();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);
  const [pendingEmail, setPendingEmail] = useState(null);
  const [retryAfter, setRetryAfter] = useState(60);

  async function submit(e) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await login(email, password);
      navigate(params.get("next") || "/");
    } catch (err) {
      const code = err.response?.data?.error?.code;
      if (code === "email_not_verified") {
        // Correct credentials, but never verified. Don't leave them stuck on a
        // generic error - a fresh code was already sent, so send them straight
        // into the OTP step with an accurate countdown.
        setRetryAfter(err.response?.data?.retry_after ?? 60);
        setPendingEmail(err.response?.data?.email ?? email);
        return;
      }
      setError(err.response?.data?.error?.message || "Couldn't sign you in. Check your details and try again.");
    } finally {
      setLoading(false);
    }
  }

  async function handleVerify(code) {
    const data = await verifyOtp({ email: pendingEmail, code });
    navigate(params.get("next") || "/", data.data?.newly_verified ? { state: { welcome: true } } : undefined);
  }

  async function handleResend() {
    return resendOtp({ email: pendingEmail, purpose: "verify" });
  }

  if (pendingEmail) {
    return (
      <Container className="max-w-sm py-14">
        <h1 className="font-display text-2xl font-semibold text-ink-950">Verify your email</h1>
        <p className="mt-1 text-sm text-ink-500">Please verify your email before signing in.</p>
        <OtpForm
          email={pendingEmail}
          onVerify={handleVerify}
          onResend={handleResend}
          submitLabel="Verify & sign in"
          initialRetryAfter={retryAfter}
        />
      </Container>
    );
  }

  return (
    <Container className="max-w-sm py-14">
      <h1 className="font-display text-2xl font-semibold text-ink-950">Welcome back</h1>
      <p className="mt-1 text-sm text-ink-500">Sign in to keep reading, liking, and bookmarking.</p>

      <div className="mt-6">
        <GoogleButton onError={setError} label="signin_with" />
      </div>

      <div className="my-5 flex items-center gap-3 text-xs text-ink-500">
        <span className="h-px flex-1 bg-ink-950/10" />
        or sign in with email
        <span className="h-px flex-1 bg-ink-950/10" />
      </div>

      <form onSubmit={submit} className="space-y-3">
        <input
          type="email" required value={email} onChange={(e) => setEmail(e.target.value)}
          placeholder="Email" autoComplete="email"
          className="w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 text-sm outline-none focus:border-gold-500"
        />
        <PasswordInput
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          placeholder="Password"
          autoComplete="current-password"
        />
        <div className="text-right">
          <Link to="/forgot-password" className="text-xs text-teal-700 hover:underline">Forgot password?</Link>
        </div>
        {error && <p className="text-sm text-ribbon-600">{error}</p>}
        <button
          type="submit" disabled={loading}
          className="w-full rounded-card bg-ink-950 py-2.5 text-sm font-medium text-parchment-50 disabled:opacity-50"
        >
          {loading ? "Signing in…" : "Sign in"}
        </button>
      </form>

      <p className="mt-5 text-center text-sm text-ink-500">
        New here? <Link to="/register" className="text-teal-700 hover:underline">Create an account</Link>
      </p>
    </Container>
  );
}

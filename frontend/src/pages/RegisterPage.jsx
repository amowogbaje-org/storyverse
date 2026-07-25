import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import Container from "../components/common/Container";
import PasswordInput from "../components/auth/PasswordInput";
import OtpForm from "../components/auth/OtpForm";
import GoogleButton from "../components/auth/GoogleButton";

export default function RegisterPage() {
  const { register, verifyOtp, resendOtp } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ name: "", email: "", password: "", password_confirmation: "" });
  const [acceptTerms, setAcceptTerms] = useState(false);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);
  const [pendingEmail, setPendingEmail] = useState(null);
  const [retryAfter, setRetryAfter] = useState(60);
  const [notice, setNotice] = useState(null);

  function update(key, value) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function submit(e) {
    e.preventDefault();
    setError(null);
    setNotice(null);

    if (form.password !== form.password_confirmation) {
      setError("Passwords don't match.");
      return;
    }
    if (!acceptTerms) {
      setError("Please agree to the Terms of Service and Privacy Policy to continue.");
      return;
    }

    setLoading(true);
    try {
      const data = await register({ ...form, accept_terms: acceptTerms });
      if (data.data?.already_registered) {
        // Not silently failing and not a duplicate account - say plainly what
        // happened and drop them straight into the OTP step with a fresh code.
        setNotice("You've already signed up with this email but haven't verified it yet. We've sent a new code.");
      }
      setRetryAfter(data.data?.retry_after ?? 60);
      setPendingEmail(data.data?.email ?? form.email);
    } catch (err) {
      setError(err.response?.data?.error?.message || "Couldn't create your account. Please check your details.");
    } finally {
      setLoading(false);
    }
  }

  async function handleVerify(code) {
    const data = await verifyOtp({ email: pendingEmail, code });
    navigate("/", data.data?.newly_verified ? { state: { welcome: true } } : undefined);
  }

  async function handleResend() {
    return resendOtp({ email: pendingEmail, purpose: "verify" });
  }

  if (pendingEmail) {
    return (
      <Container className="max-w-sm py-14">
        <h1 className="font-display text-2xl font-semibold text-ink-950">Verify your email</h1>
        {notice && <p className="mt-2 text-sm text-teal-700">{notice}</p>}
        <OtpForm
          email={pendingEmail}
          onVerify={handleVerify}
          onResend={handleResend}
          submitLabel="Verify & continue"
          initialRetryAfter={retryAfter}
        />
      </Container>
    );
  }

  return (
    <Container className="max-w-sm py-14">
      <h1 className="font-display text-2xl font-semibold text-ink-950">Join Storyverse</h1>
      <p className="mt-1 text-sm text-ink-500">
        Free — unlocks more free episodes on every story, likes, bookmarks, and comments.
      </p>

      <div className="mt-6">
        <GoogleButton onError={setError} label="signup_with" />
      </div>

      <div className="my-5 flex items-center gap-3 text-xs text-ink-500">
        <span className="h-px flex-1 bg-ink-950/10" />
        or sign up with email
        <span className="h-px flex-1 bg-ink-950/10" />
      </div>

      <form onSubmit={submit} className="space-y-3">
        <input
          required value={form.name} onChange={(e) => update("name", e.target.value)}
          placeholder="Display name" autoComplete="name"
          className="w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 text-sm outline-none focus:border-gold-500"
        />
        <input
          type="email" required value={form.email} onChange={(e) => update("email", e.target.value)}
          placeholder="Email" autoComplete="email"
          className="w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 text-sm outline-none focus:border-gold-500"
        />
        <PasswordInput
          value={form.password}
          onChange={(e) => update("password", e.target.value)}
          placeholder="Password (min. 8 characters)"
          autoComplete="new-password"
        />
        <PasswordInput
          value={form.password_confirmation}
          onChange={(e) => update("password_confirmation", e.target.value)}
          placeholder="Confirm password"
          autoComplete="new-password"
        />

        <label className="flex items-start gap-2 text-xs text-ink-500">
          <input
            type="checkbox"
            checked={acceptTerms}
            onChange={(e) => setAcceptTerms(e.target.checked)}
            className="mt-0.5"
          />
          <span>
            I agree to Storyverse's{" "}
            <Link to="/terms" className="text-teal-700 hover:underline" target="_blank" rel="noopener noreferrer">
              Terms of Service
            </Link>{" "}
            and{" "}
            <Link to="/privacy" className="text-teal-700 hover:underline" target="_blank" rel="noopener noreferrer">
              Privacy Policy
            </Link>
            .
          </span>
        </label>

        {error && <p className="text-sm text-ribbon-600">{error}</p>}
        <button
          type="submit" disabled={loading}
          className="w-full rounded-card bg-ink-950 py-2.5 text-sm font-medium text-parchment-50 disabled:opacity-50"
        >
          {loading ? "Creating account…" : "Create account"}
        </button>
      </form>

      <p className="mt-5 text-center text-sm text-ink-500">
        Already have an account? <Link to="/login" className="text-teal-700 hover:underline">Sign in</Link>
      </p>
    </Container>
  );
}

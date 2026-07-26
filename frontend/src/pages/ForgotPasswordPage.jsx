import { useEffect, useRef, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import Container from "../components/common/Container";

export default function ForgotPasswordPage() {
  const { forgotPassword, resetPassword } = useAuth();
  const navigate = useNavigate();

  const [step, setStep] = useState("email"); // "email" | "reset"
  const [email, setEmail] = useState("");
  const [code, setCode] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [notice, setNotice] = useState(null);
  const [secondsLeft, setSecondsLeft] = useState(60);
  const tickRef = useRef(null);

  useEffect(() => {
    if (secondsLeft <= 0) return;
    tickRef.current = window.setTimeout(() => setSecondsLeft((s) => s - 1), 1000);
    return () => window.clearTimeout(tickRef.current);
  }, [secondsLeft]);

  async function submitEmail(e) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const data = await forgotPassword(email);
      // Always the same message and countdown regardless of whether the email
      // is real - see AuthController::forgotPassword()'s docblock for why:
      // this endpoint is deliberately non-committal about account existence.
      setNotice(data.data?.message);
      setSecondsLeft(data.data?.retry_after ?? 60);
      setStep("reset");
    } catch {
      setError("Something went wrong. Please try again.");
    } finally {
      setLoading(false);
    }
  }

  async function resend() {
    if (secondsLeft > 0) return;
    setNotice(null);
    try {
      const data = await forgotPassword(email);
      setNotice(data.data?.message);
      setSecondsLeft(data.data?.retry_after ?? 60);
    } catch {
      setError("Couldn't resend. Please try again shortly.");
    }
  }

  async function submitReset(e) {
    e.preventDefault();
    setError(null);

    if (password !== passwordConfirmation) {
      setError("Passwords don't match.");
      return;
    }

    setLoading(true);
    try {
      await resetPassword({ email, code, password, password_confirmation: passwordConfirmation });
      navigate("/");
    } catch (err) {
      setError(err.response?.data?.error?.message || "That code is invalid or expired.");
    } finally {
      setLoading(false);
    }
  }

  if (step === "email") {
    return (
      <Container className="max-w-sm py-14">
        <h1 className="font-display text-2xl font-semibold text-ink-950">Forgot your password?</h1>
        <p className="mt-1 text-sm text-ink-500">Enter your email and we'll send you a reset code.</p>

        <form onSubmit={submitEmail} className="mt-6 space-y-3">
          <input
            type="email" required value={email} onChange={(e) => setEmail(e.target.value)}
            placeholder="Email" autoComplete="email"
            className="w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 text-sm outline-none focus:border-gold-500"
          />
          {error && <p className="text-sm text-ribbon-600">{error}</p>}
          <button
            type="submit" disabled={loading}
            className="w-full rounded-card bg-ink-950 py-2.5 text-sm font-medium text-parchment-50 disabled:opacity-50"
          >
            {loading ? "Sending…" : "Send reset code"}
          </button>
        </form>

        <p className="mt-5 text-center text-sm text-ink-500">
          <Link to="/login" className="text-teal-700 hover:underline">Back to sign in</Link>
        </p>
      </Container>
    );
  }

  return (
    <Container className="max-w-sm py-14">
      <h1 className="font-display text-2xl font-semibold text-ink-950">Reset your password</h1>
      {notice && <p className="mt-2 text-sm text-ink-500">{notice}</p>}

      <form onSubmit={submitReset} className="mt-6 space-y-3">
        <input
          required inputMode="numeric" pattern="[0-9]{6}" maxLength={6}
          value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ""))}
          placeholder="123456"
          className="w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 text-center text-lg tracking-[0.5em] outline-none focus:border-gold-500"
        />
        <input
          type="password" required value={password} onChange={(e) => setPassword(e.target.value)}
          placeholder="New password" autoComplete="new-password"
          className="w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 text-sm outline-none focus:border-gold-500"
        />
        <input
          type="password" required value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)}
          placeholder="Confirm new password" autoComplete="new-password"
          className="w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 text-sm outline-none focus:border-gold-500"
        />
        {error && <p className="text-sm text-ribbon-600">{error}</p>}
        <button
          type="submit" disabled={loading || code.length !== 6}
          className="w-full rounded-card bg-ink-950 py-2.5 text-sm font-medium text-parchment-50 disabled:opacity-50"
        >
          {loading ? "Resetting…" : "Reset password & sign in"}
        </button>
        <button
          type="button" onClick={resend} disabled={secondsLeft > 0}
          className="w-full text-center text-sm text-teal-700 hover:underline disabled:cursor-not-allowed disabled:text-ink-300 disabled:no-underline"
        >
          {secondsLeft > 0 ? `Resend code in ${secondsLeft}s` : "Resend code"}
        </button>
      </form>
    </Container>
  );
}

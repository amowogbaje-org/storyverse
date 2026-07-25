import { useEffect, useRef, useState } from "react";

export default function OtpForm({ email, onVerify, onResend, submitLabel = "Verify", initialRetryAfter = 60 }) {
  const [code, setCode] = useState("");
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);
  const [resent, setResent] = useState(false);
  const [secondsLeft, setSecondsLeft] = useState(initialRetryAfter);
  const tickRef = useRef(null);

  useEffect(() => {
    setSecondsLeft(initialRetryAfter);
  }, [initialRetryAfter]);

  useEffect(() => {
    if (secondsLeft <= 0) return;
    tickRef.current = window.setTimeout(() => setSecondsLeft((s) => s - 1), 1000);
    return () => window.clearTimeout(tickRef.current);
  }, [secondsLeft]);

  async function submit(e) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await onVerify(code);
    } catch (err) {
      setError(err.response?.data?.error?.message || "That code is invalid or expired.");
    } finally {
      setLoading(false);
    }
  }

  async function resend() {
    if (secondsLeft > 0) return;
    setError(null);
    setResent(false);
    try {
      const data = await onResend();
      setResent(true);
      setSecondsLeft(data?.data?.retry_after ?? 60);
    } catch (err) {
      // The server enforces the same 1-minute limit - if we somehow get here
      // early (e.g. a second tab), trust its retry_after over our own clock.
      const retryAfter = err.response?.data?.retry_after;
      if (retryAfter) setSecondsLeft(retryAfter);
      setError(err.response?.data?.error?.message || "Couldn't resend the code. Please try again shortly.");
    }
  }

  return (
    <form onSubmit={submit} className="mt-6 space-y-3">
      <p className="text-sm text-ink-500">
        We sent a 6-digit code to <span className="font-medium text-ink-900">{email}</span>. Enter it below to confirm your email.
        The code expires in 1 minute, so grab it as soon as it lands.
      </p>
      <input
        required
        inputMode="numeric"
        pattern="[0-9]{6}"
        maxLength={6}
        value={code}
        onChange={(e) => setCode(e.target.value.replace(/\D/g, ""))}
        placeholder="123456"
        className="w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 text-center text-lg tracking-[0.5em] outline-none focus:border-gold-500"
      />
      {error && <p className="text-sm text-ribbon-600">{error}</p>}
      {resent && secondsLeft > 0 && <p className="text-sm text-teal-700">A new code is on its way.</p>}
      <button
        type="submit"
        disabled={loading || code.length !== 6}
        className="w-full rounded-card bg-ink-950 py-2.5 text-sm font-medium text-parchment-50 disabled:opacity-50"
      >
        {loading ? "Verifying…" : submitLabel}
      </button>
      <button
        type="button"
        onClick={resend}
        disabled={secondsLeft > 0}
        className="w-full text-center text-sm text-teal-700 hover:underline disabled:cursor-not-allowed disabled:text-ink-300 disabled:no-underline"
      >
        {secondsLeft > 0 ? `Resend code in ${secondsLeft}s` : "Resend code"}
      </button>
    </form>
  );
}

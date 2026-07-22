import { useState } from "react";

export default function OtpForm({ email, onVerify, onResend, submitLabel = "Verify" }) {
  const [code, setCode] = useState("");
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);
  const [resent, setResent] = useState(false);

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
    setError(null);
    setResent(false);
    try {
      await onResend();
      setResent(true);
    } catch {
      setError("Couldn't resend the code. Please try again shortly.");
    }
  }

  return (
    <form onSubmit={submit} className="mt-6 space-y-3">
      <p className="text-sm text-ink-500">
        We sent a 6-digit code to <span className="font-medium text-ink-900">{email}</span>. Enter it below to confirm your email.
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
      {resent && <p className="text-sm text-teal-700">A new code is on its way.</p>}
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
        className="w-full text-center text-sm text-teal-700 hover:underline"
      >
        Resend code
      </button>
    </form>
  );
}

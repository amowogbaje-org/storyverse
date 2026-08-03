import { useState } from "react";
import { useAuth } from "../../context/AuthContext";
import api from "../../api/client";

export default function PasswordSettings() {
  const { user, refresh } = useAuth();
  const [currentPassword, setCurrentPassword] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState(null);

  const hasPassword = Boolean(user?.has_password);

  async function save(e) {
    e.preventDefault();
    setError(null);
    setSaving(true);
    try {
      await api.patch("/me/password", {
        current_password: hasPassword ? currentPassword : undefined,
        password,
        password_confirmation: passwordConfirmation,
      });
      // Signing in with Google never touches this - it just adds a
      // password on top, so Google sign-in keeps working exactly as before.
      await refresh();
      setCurrentPassword("");
      setPassword("");
      setPasswordConfirmation("");
      setSaved(true);
      window.setTimeout(() => setSaved(false), 2000);
    } catch (err) {
      setError(err.response?.data?.error?.message || "Couldn't update your password.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="mt-4 rounded-card border border-ink-950/10 bg-white/40 p-4">
      <p className="text-sm font-medium text-ink-950">{hasPassword ? "Change password" : "Set a password"}</p>
      <p className="mt-0.5 text-xs text-ink-500">
        {hasPassword
          ? "Update the password you use to sign in directly. Signing in with Google still works the same either way."
          : "Your account currently only signs in via Google. Add a password to also be able to sign in directly with your email."}
      </p>

      <form onSubmit={save} className="mt-3 space-y-2">
        {hasPassword && (
          <input
            type="password"
            value={currentPassword}
            onChange={(e) => setCurrentPassword(e.target.value)}
            placeholder="Current password"
            required
            className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm"
          />
        )}
        <input
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          placeholder="New password"
          minLength={8}
          required
          className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm"
        />
        <input
          type="password"
          value={passwordConfirmation}
          onChange={(e) => setPasswordConfirmation(e.target.value)}
          placeholder="Confirm new password"
          minLength={8}
          required
          className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm"
        />
        {error && <p className="text-xs text-ribbon-600">{error}</p>}
        {saved && <p className="text-xs text-teal-700">Saved.</p>}
        <button
          type="submit"
          disabled={saving}
          className="rounded-card bg-ink-950 px-4 py-2 text-xs font-medium text-parchment-50 disabled:opacity-50"
        >
          {saving ? "Saving…" : hasPassword ? "Update password" : "Set password"}
        </button>
      </form>
    </div>
  );
}

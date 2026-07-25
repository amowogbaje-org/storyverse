import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import api from "../api/client";
import Container from "../components/common/Container";
import { isPushSupported, subscribeToPush, unsubscribeFromPush, getCurrentPushSubscription } from "../utils/push";

const CURRENCIES = [
  { code: "USD", label: "USD ($) — United States" },
  { code: "GBP", label: "GBP (£) — United Kingdom" },
  { code: "NGN", label: "NGN (₦) — Nigeria" },
];

const NOTIFICATION_TYPES = [
  { key: "new_story_push", label: "New story recommendations", hint: "Based on categories you've liked, bookmarked, or finished." },
  { key: "continue_reading_push", label: "Continue reading reminders", hint: "A nudge about a story you left partway through." },
  { key: "missed_you_push", label: "\"We missed you\" nudges", hint: "Only sent after a week or more away." },
  { key: "badge_push", label: "Badge unlock alerts (push)", hint: "" },
  { key: "badge_emails", label: "Badge unlock emails", hint: "" },
];

export default function ProfilePage() {
  const { user, refresh } = useAuth();
  const navigate = useNavigate();
  const [displayName, setDisplayName] = useState("");
  const [currency, setCurrency] = useState("USD");
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [becomingAuthor, setBecomingAuthor] = useState(false);
  const [pushEnabled, setPushEnabled] = useState(false);
  const [pushBusy, setPushBusy] = useState(false);
  const [pushError, setPushError] = useState(null);
  const [prefs, setPrefs] = useState({});
  const [prefBusyKey, setPrefBusyKey] = useState(null);

  useEffect(() => {
    if (!isPushSupported()) return;
    getCurrentPushSubscription().then((sub) => setPushEnabled(Boolean(sub)));
  }, []);

  async function togglePush() {
    setPushBusy(true);
    setPushError(null);
    try {
      if (pushEnabled) {
        await unsubscribeFromPush();
        setPushEnabled(false);
      } else {
        await subscribeToPush();
        setPushEnabled(true);
      }
    } catch (err) {
      setPushError(err.message === "Notification permission was not granted."
        ? "You'll need to allow notifications in your browser to turn this on."
        : "Couldn't update push notifications. Please try again.");
    } finally {
      setPushBusy(false);
    }
  }

  // Every preference defaults to on until the person has actually touched it -
  // matches the backend's "missing key = true" reading in each Notification's via().
  async function togglePreference(key) {
    const next = !(prefs[key] ?? true);
    setPrefBusyKey(key);
    setPrefs((p) => ({ ...p, [key]: next })); // optimistic
    try {
      await api.patch("/me/notification-preferences", { preferences: { [key]: next } });
      await refresh();
    } catch {
      setPrefs((p) => ({ ...p, [key]: !next })); // revert on failure
    } finally {
      setPrefBusyKey(null);
    }
  }

  // Keeps the form in sync with the real user object rather than only reading
  // it once at mount. Two different bugs otherwise: (1) if `user` is still
  // null on the very first render (auth still loading) and only arrives on a
  // later re-render without this component unmounting in between, a
  // mount-only useState initializer never picks it up; (2) the previous code
  // read `user.currency_code`, but the API actually returns the field as
  // `currency` - so the form was silently falling back to the hardcoded
  // default every time, no matter what was actually saved.
  useEffect(() => {
    if (!user) return;
    setDisplayName(user.display_name ?? "");
    setCurrency(user.currency ?? "USD");
    setPrefs(user.notification_preferences ?? {});
  }, [user]);

  async function becomeAuthor() {
    setBecomingAuthor(true);
    try {
      await api.post("/me/become-author");
      await refresh();
      navigate("/admin");
    } finally {
      setBecomingAuthor(false);
    }
  }

  async function save(e) {
    e.preventDefault();
    setSaving(true);
    setSaved(false);
    try {
      await api.patch("/me", { name: displayName, currency_code: currency });
      await refresh();
      setSaved(true);
    } finally {
      setSaving(false);
    }
  }

  if (!user) return null;

  return (
    <Container className="max-w-md py-10">
      <h1 className="font-display text-2xl font-semibold text-ink-950">Settings</h1>

      <form onSubmit={save} className="mt-6 space-y-4">
        <div>
          <label className="mb-1 block text-xs font-medium text-ink-500">Display name</label>
          <input
            value={displayName} onChange={(e) => setDisplayName(e.target.value)}
            className="w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 text-sm outline-none focus:border-gold-500"
          />
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium text-ink-500">Billing currency</label>
          <select
            value={currency} onChange={(e) => setCurrency(e.target.value)}
            className="w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 text-sm outline-none focus:border-gold-500"
          >
            {CURRENCIES.map((c) => <option key={c.code} value={c.code}>{c.label}</option>)}
          </select>
          <p className="mt-1 text-xs text-ink-500">Detected automatically at sign-up; change it any time.</p>
        </div>
        <div className="rounded-card border border-ink-950/10 bg-white/40 p-3 text-xs text-ink-500">
          Email: {user.email}
        </div>
        {saved && <p className="text-sm text-teal-700">Saved.</p>}
        <button
          type="submit" disabled={saving}
          className="rounded-card bg-ink-950 px-5 py-2.5 text-sm font-medium text-parchment-50 disabled:opacity-50"
        >
          {saving ? "Saving…" : "Save changes"}
        </button>
      </form>

      <div className="mt-6 rounded-card border border-ink-950/10 bg-white/40 p-4">
        <div className="flex items-center justify-between gap-3">
          <div>
            <p className="text-sm font-medium text-ink-950">Push notifications</p>
            <p className="mt-0.5 text-xs text-ink-500">
              Badge unlocks, story recommendations, and reminders to continue what you're reading.
            </p>
          </div>
          {isPushSupported() ? (
            <button
              type="button"
              onClick={togglePush}
              disabled={pushBusy}
              className={`shrink-0 rounded-full px-4 py-1.5 text-xs font-medium disabled:opacity-50 ${
                pushEnabled ? "border border-ink-950/15 text-ink-700" : "bg-ink-950 text-parchment-50"
              }`}
            >
              {pushBusy ? "…" : pushEnabled ? "Turn off" : "Turn on"}
            </button>
          ) : (
            <span className="shrink-0 text-xs text-ink-400">Not supported in this browser</span>
          )}
        </div>
        {pushError && <p className="mt-2 text-xs text-ribbon-600">{pushError}</p>}
      </div>

      <div className="mt-4 rounded-card border border-ink-950/10 bg-white/40 p-4">
        <p className="text-sm font-medium text-ink-950">Notification types</p>
        <p className="mt-0.5 text-xs text-ink-500">Turn off anything you'd rather not hear about.</p>
        <ul className="mt-3 divide-y divide-ink-950/8">
          {NOTIFICATION_TYPES.map((t) => {
            const enabled = prefs[t.key] ?? true;
            return (
              <li key={t.key} className="flex items-center justify-between gap-3 py-2.5">
                <div className="min-w-0">
                  <p className="text-sm text-ink-900">{t.label}</p>
                  {t.hint && <p className="text-xs text-ink-500">{t.hint}</p>}
                </div>
                <button
                  type="button"
                  role="switch"
                  aria-checked={enabled}
                  onClick={() => togglePreference(t.key)}
                  disabled={prefBusyKey === t.key}
                  className={`relative h-6 w-10 shrink-0 rounded-full transition-colors disabled:opacity-50 ${
                    enabled ? "bg-gold-500" : "bg-ink-950/15"
                  }`}
                >
                  <span
                    className={`absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform ${
                      enabled ? "translate-x-4" : "translate-x-0"
                    }`}
                  />
                </button>
              </li>
            );
          })}
        </ul>
      </div>

      {/* {user.role === "reader" && (
        <div className="mt-6 rounded-card border border-gold-500/40 bg-gold-400/10 p-4">
          <p className="text-sm font-medium text-ink-950">Want to publish your own stories?</p>
          <p className="mt-1 text-xs text-ink-600">
            Switch to an author account to create pen names, write stories and episodes,
            and see your stats and earnings.
          </p>
          <button
            onClick={becomeAuthor} disabled={becomingAuthor}
            className="mt-3 rounded-full bg-gold-500 px-4 py-1.5 text-sm font-semibold text-ink-950 disabled:opacity-50"
          >
            {becomingAuthor ? "Switching…" : "Become an author"}
          </button>
        </div>
      )} */}
    </Container>
  );
}

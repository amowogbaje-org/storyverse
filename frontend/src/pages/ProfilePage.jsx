import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import api from "../api/client";
import Container from "../components/common/Container";

const CURRENCIES = [
  { code: "USD", label: "USD ($) — United States" },
  { code: "GBP", label: "GBP (£) — United Kingdom" },
  { code: "NGN", label: "NGN (₦) — Nigeria" },
];

export default function ProfilePage() {
  const { user, refresh } = useAuth();
  const navigate = useNavigate();
  const [displayName, setDisplayName] = useState("");
  const [currency, setCurrency] = useState("USD");
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [becomingAuthor, setBecomingAuthor] = useState(false);

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

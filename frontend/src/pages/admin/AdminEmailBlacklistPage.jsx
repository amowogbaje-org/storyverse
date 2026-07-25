import { useState } from "react";
import { useAdminEmailBlacklist } from "../../hooks/queries/useAdmin";
import { useAddToBlacklist, useRemoveFromBlacklist } from "../../hooks/mutations/useAdminMutations";
import { useAuth } from "../../context/AuthContext";
import LoadingSpinner from "../../components/common/LoadingSpinner";

export default function AdminEmailBlacklistPage() {
  const { user } = useAuth();
  const { data, isLoading } = useAdminEmailBlacklist(user?.role === "admin");
  const addEntry = useAddToBlacklist();
  const removeEntry = useRemoveFromBlacklist();
  const [email, setEmail] = useState("");
  const [reason, setReason] = useState("");
  const [error, setError] = useState(null);

  // The nav link is already hidden for non-admins (AdminLayout), but this page
  // reaches an admin-only backend endpoint (RequireAdmin middleware, not the
  // looser author_or_admin used elsewhere in /admin) - guard it here too
  // rather than letting an author who navigates here directly just see failed
  // API calls.
  if (user?.role !== "admin") {
    return <p className="text-sm text-ink-500">This page is only available to platform admins.</p>;
  }

  // EmailBlacklistController::index() returns a Laravel paginator, so the
  // actual rows are one level deeper than the usual `data.data` shape.
  const entries = data?.data?.data ?? [];

  async function submit(e) {
    e.preventDefault();
    setError(null);
    try {
      await addEntry.mutateAsync({ email: email.trim(), reason: reason.trim() || null });
      setEmail("");
      setReason("");
    } catch (err) {
      setError(err.response?.data?.error?.message || "Couldn't add that email.");
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="font-display text-lg font-semibold text-ink-950">Email blacklist</h2>
        <p className="mt-1 max-w-xl text-sm text-ink-500">
          Mail-risk mitigation while there's no transactional email provider wired up: if a
          verification/reset email hard-bounces off an address, add it here. Anyone trying to
          register or sign in with a blacklisted address gets told plainly that it looks invalid,
          instead of us quietly failing to deliver mail to it.
        </p>
      </div>

      <form onSubmit={submit} className="flex flex-wrap items-end gap-3 rounded-card border border-ink-950/10 bg-white/60 p-4">
        <div className="min-w-[220px] flex-1">
          <label className="mb-1 block text-xs font-medium text-ink-500">Email address</label>
          <input
            required type="email" value={email} onChange={(e) => setEmail(e.target.value)}
            placeholder="bounced@example.com"
            className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm"
          />
        </div>
        <div className="min-w-[220px] flex-1">
          <label className="mb-1 block text-xs font-medium text-ink-500">Reason (optional)</label>
          <input
            value={reason} onChange={(e) => setReason(e.target.value)}
            placeholder="e.g. hard bounce, verification email"
            className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm"
          />
        </div>
        <button
          type="submit" disabled={addEntry.isPending}
          className="rounded-card bg-ink-950 px-4 py-2 text-sm font-medium text-parchment-50 disabled:opacity-50"
        >
          {addEntry.isPending ? "Adding…" : "Add to blacklist"}
        </button>
      </form>
      {error && <p className="text-sm text-ribbon-600">{error}</p>}

      {isLoading ? (
        <LoadingSpinner />
      ) : (
        <ul className="divide-y divide-ink-950/8 rounded-card border border-ink-950/10 bg-white/60">
          {entries.map((entry) => (
            <li key={entry.id} className="flex items-center justify-between gap-3 px-4 py-3">
              <div className="min-w-0">
                <p className="truncate text-sm font-medium text-ink-950">{entry.email}</p>
                <p className="text-xs text-ink-500">
                  {entry.reason || "No reason given"} · added {new Date(entry.created_at).toLocaleDateString()}
                </p>
              </div>
              <button
                onClick={() => removeEntry.mutate(entry.email)}
                disabled={removeEntry.isPending}
                className="shrink-0 rounded-full border border-ink-950/15 px-3 py-1 text-xs font-medium text-ink-700 hover:border-ribbon-400 hover:text-ribbon-600 disabled:opacity-50"
              >
                Remove
              </button>
            </li>
          ))}
          {!entries.length && (
            <li className="px-4 py-6 text-center text-sm text-ink-500">No blacklisted emails yet.</li>
          )}
        </ul>
      )}
    </div>
  );
}

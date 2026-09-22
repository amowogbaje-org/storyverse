import { useState } from "react";
import { useAuth } from "../../context/AuthContext";
import { useAdminUsers } from "../../hooks/queries/useAdmin";
import { useGrantAuthor, useRejectAuthorRequest, useRevokeAuthor } from "../../hooks/mutations/useAdminMutations";
import LoadingSpinner from "../../components/common/LoadingSpinner";

const TABS = [
  { key: "pending", label: "Pending requests", params: { status: "pending" } },
  { key: "author", label: "Authors", params: { role: "author" } },
  { key: "reader", label: "Readers", params: { role: "reader" } },
  { key: "all", label: "All", params: {} },
];

export default function AdminUsersPage() {
  const { user } = useAuth();
  const [tab, setTab] = useState("pending");
  const [q, setQ] = useState("");
  const [revokeTarget, setRevokeTarget] = useState(null); // { id, name } while the confirm dialog is open

  const filters = { ...TABS.find((t) => t.key === tab).params, ...(q.trim() ? { q: q.trim() } : {}) };
  const { data, isLoading } = useAdminUsers(filters, user?.role === "admin");

  const grant = useGrantAuthor();
  const reject = useRejectAuthorRequest();
  const revoke = useRevokeAuthor();

  if (user?.role !== "admin") {
    return <p className="text-sm text-ink-500">This page is only available to platform admins.</p>;
  }

  const users = data?.data ?? [];

  async function confirmRevoke(unpublishStories) {
    if (!revokeTarget) return;
    await revoke.mutateAsync({ id: revokeTarget.id, unpublishStories });
    setRevokeTarget(null);
  }

  return (
    <div className="space-y-4">
      <div>
        <h2 className="font-display text-lg font-semibold text-ink-950">Users</h2>
        <p className="mt-1 max-w-xl text-sm text-ink-500">
          Grant author access to a reader who's requested it (or promote one directly), decline a request, or move
          an author back to reader - which also unpublishes their stories unless you say otherwise.
        </p>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-1.5">
          {TABS.map((t) => (
            <button
              key={t.key}
              onClick={() => setTab(t.key)}
              className={`rounded-full border px-3 py-1 text-xs font-medium ${
                tab === t.key ? "border-ink-950 bg-ink-950 text-parchment-50" : "border-ink-950/15 text-ink-700"
              }`}
            >
              {t.label}
            </button>
          ))}
        </div>
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Search name or email…"
          className="w-56 rounded-card border border-ink-950/15 bg-white px-3 py-1.5 text-sm"
        />
      </div>

      {isLoading ? (
        <LoadingSpinner />
      ) : (
        <ul className="divide-y divide-ink-950/8 rounded-card border border-ink-950/10 bg-white/60">
          {users.map((u) => (
            <li key={u.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
              <div className="min-w-0">
                <p className="truncate text-sm font-medium text-ink-950">{u.name}</p>
                <p className="truncate text-xs text-ink-500">{u.email}</p>
                <div className="mt-1 flex items-center gap-2">
                  <span className="rounded-full bg-ink-950/5 px-2 py-0.5 text-[10px] font-medium capitalize text-ink-700">
                    {u.role}
                  </span>
                  {u.author_request_status === "pending" && (
                    <span className="rounded-full bg-gold-400/20 px-2 py-0.5 text-[10px] font-medium text-gold-700">
                      Requested {new Date(u.author_requested_at).toLocaleDateString()}
                    </span>
                  )}
                  {u.author_request_status === "rejected" && (
                    <span className="rounded-full bg-ribbon-500/10 px-2 py-0.5 text-[10px] font-medium text-ribbon-600">
                      Declined
                    </span>
                  )}
                </div>
              </div>

              <div className="flex shrink-0 gap-1.5">
                {u.role !== "author" && u.role !== "admin" && (
                  <button
                    onClick={() => grant.mutate(u.id)}
                    disabled={grant.isPending}
                    className="rounded-full bg-ink-950 px-3 py-1 text-xs font-medium text-parchment-50 disabled:opacity-50"
                  >
                    Grant author
                  </button>
                )}
                {u.author_request_status === "pending" && (
                  <button
                    onClick={() => reject.mutate(u.id)}
                    disabled={reject.isPending}
                    className="rounded-full border border-ink-950/15 px-3 py-1 text-xs text-ink-700 disabled:opacity-50"
                  >
                    Decline
                  </button>
                )}
                {u.role === "author" && (
                  <button
                    onClick={() => setRevokeTarget({ id: u.id, name: u.name })}
                    className="rounded-full border border-ribbon-400 px-3 py-1 text-xs font-medium text-ribbon-600"
                  >
                    Revoke
                  </button>
                )}
              </div>
            </li>
          ))}
          {!users.length && (
            <li className="px-4 py-8 text-center text-sm text-ink-500">No users match this filter.</li>
          )}
        </ul>
      )}

      {revokeTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/40 p-4">
          <div className="w-full max-w-sm rounded-card border border-ink-950/10 bg-parchment-50 p-5">
            <p className="text-sm font-medium text-ink-950">Revoke {revokeTarget.name}'s author access?</p>
            <p className="mt-1 text-xs text-ink-500">
              They'll go back to a reader account. Their published stories can either come down with them or stay
              live under their existing pen name.
            </p>
            <div className="mt-4 flex flex-col gap-2">
              <button
                onClick={() => confirmRevoke(true)}
                disabled={revoke.isPending}
                className="rounded-card bg-ribbon-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
              >
                Revoke and unpublish their stories
              </button>
              <button
                onClick={() => confirmRevoke(false)}
                disabled={revoke.isPending}
                className="rounded-card border border-ink-950/15 px-3 py-2 text-sm text-ink-700 disabled:opacity-50"
              >
                Revoke but leave stories published
              </button>
              <button
                onClick={() => setRevokeTarget(null)}
                disabled={revoke.isPending}
                className="px-3 py-1 text-xs text-ink-500"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

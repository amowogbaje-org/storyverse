import { useState } from "react";
import { useAdminPenNames } from "../../hooks/queries/useAdmin";
import { useCreatePenName } from "../../hooks/mutations/useAdminMutations";
import LoadingSpinner from "../../components/common/LoadingSpinner";
import EmptyState from "../../components/common/EmptyState";

export default function AdminPenNamesPage() {
  const { data, isLoading } = useAdminPenNames(true);
  const createPenName = useCreatePenName();
  const [displayName, setDisplayName] = useState("");
  const [bio, setBio] = useState("");

  const penNames = data?.data ?? [];

  function submit(e) {
    e.preventDefault();
    if (!displayName.trim()) return;
    createPenName.mutate(
      { display_name: displayName.trim(), bio: bio.trim() || undefined },
      { onSuccess: () => { setDisplayName(""); setBio(""); } }
    );
  }

  return (
    <div className="grid gap-6 md:grid-cols-[1fr_320px]">
      <div>
        <h2 className="mb-3 font-display text-lg font-semibold text-ink-950">Your pen names</h2>
        {isLoading ? (
          <LoadingSpinner />
        ) : penNames.length ? (
          <ul className="space-y-2">
            {penNames.map((p) => (
              <li key={p.id} className="flex items-center gap-3 rounded-card border border-ink-950/10 bg-white/60 p-3">
                <span className="grid h-10 w-10 place-items-center rounded-full bg-teal-700 text-sm font-semibold text-parchment-50">
                  {p.display_name?.[0]?.toUpperCase()}
                </span>
                <div className="min-w-0 flex-1">
                  <p className="font-medium text-ink-950">
                    {p.display_name} {p.is_default && <span className="ml-1 rounded-full bg-gold-400/30 px-2 py-0.5 text-[10px] font-semibold text-gold-600">Default</span>}
                  </p>
                  {p.bio && <p className="truncate text-xs text-ink-500">{p.bio}</p>}
                </div>
              </li>
            ))}
          </ul>
        ) : (
          <EmptyState title="No pen names yet" hint="Create one to start publishing stories." />
        )}
      </div>

      <div>
        <h2 className="mb-3 font-display text-lg font-semibold text-ink-950">New pen name</h2>
        <form onSubmit={submit} className="space-y-3 rounded-card border border-ink-950/10 bg-white/60 p-4">
          <input
            value={displayName} onChange={(e) => setDisplayName(e.target.value)}
            placeholder="Display name" required
            className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm outline-none focus:border-gold-500"
          />
          <textarea
            value={bio} onChange={(e) => setBio(e.target.value)}
            placeholder="Short bio (optional)" rows={3}
            className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm outline-none focus:border-gold-500"
          />
          <button
            type="submit" disabled={createPenName.isPending}
            className="w-full rounded-card bg-ink-950 py-2 text-sm font-medium text-parchment-50 disabled:opacity-50"
          >
            {createPenName.isPending ? "Creating…" : "Create pen name"}
          </button>
        </form>
      </div>
    </div>
  );
}

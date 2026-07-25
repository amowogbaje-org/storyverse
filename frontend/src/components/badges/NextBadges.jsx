import { Link } from "react-router-dom";
import { useAuth } from "../../context/AuthContext";
import { useNextBadges } from "../../hooks/queries/useBadges";

/** @param {"compact"|"full"} variant - "compact" for a homepage teaser, "full" for the badges page */
export default function NextBadges({ variant = "full", limit = 3 }) {
  const { isAuthenticated } = useAuth();
  const { data, isLoading } = useNextBadges(isAuthenticated, limit);
  const badges = data?.data ?? [];

  if (!isAuthenticated || isLoading || !badges.length) return null;

  if (variant === "compact") {
    const top = badges[0];
    return (
      <Link
        to="/badges"
        className="mb-6 flex items-center gap-3 rounded-card border border-gold-500/40 bg-gold-400/10 px-4 py-3 text-sm text-ink-900 hover:bg-gold-400/20"
      >
        <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gold-500 text-sm">★</span>
        <span className="min-w-0 flex-1">
          <span className="font-medium">{top.remaining} more to unlock "{top.name}"</span>
          <span className="block text-xs text-ink-600">{top.description}</span>
        </span>
        <span className="shrink-0 text-xs font-semibold text-gold-600">{top.progress_percent}%</span>
      </Link>
    );
  }

  return (
    <div className="mb-6">
      <h2 className="mb-3 font-display text-lg font-semibold text-ink-950">Closest to unlocking</h2>
      <div className="grid gap-3 sm:grid-cols-3">
        {badges.map((b) => (
          <div key={b.id} className="rounded-card border border-ink-950/10 bg-white/60 p-3">
            <div className="flex items-center gap-2">
              <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gold-500/20 text-base text-gold-700">★</span>
              <div className="min-w-0">
                <p className="truncate text-sm font-medium text-ink-950">{b.name}</p>
                <p className="text-xs capitalize text-ink-500">{b.tier} · {b.category}</p>
              </div>
            </div>
            <p className="mt-2 text-xs text-ink-600">{b.description}</p>
            <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-ink-950/8">
              <div className="h-full rounded-full bg-gold-500" style={{ width: `${b.progress_percent}%` }} />
            </div>
            <p className="mt-1 text-[11px] text-ink-500">
              {b.progress_current}/{b.criteria_value} · {b.remaining} to go
            </p>
          </div>
        ))}
      </div>
    </div>
  );
}

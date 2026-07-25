export default function BadgeGrid({ badges, earnedIds = [] }) {
  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
      {badges.map((b) => {
        const earned = earnedIds.includes(b.id);
        const showProgress = !earned && typeof b.progress_percent === "number";

        return (
          <div
            key={b.id}
            className={`flex flex-col items-center gap-1.5 rounded-card border p-3 text-center ${
              earned ? "border-gold-500/50 bg-gold-400/10" : "border-ink-950/8 bg-white/40"
            }`}
          >
            <div
              className={`grid h-12 w-12 place-items-center rounded-full text-lg ${
                earned ? "bg-gold-500 text-ink-950" : "bg-ink-950/10 text-ink-300"
              }`}
            >
              {b.icon ?? "★"}
            </div>
            <p className={`text-[11px] font-medium leading-tight ${earned ? "text-ink-900" : "text-ink-700"}`}>
              {b.name}
            </p>
            <p className="text-[10px] leading-tight text-ink-500">{b.description}</p>

            {showProgress && (
              <div className="mt-1 w-full">
                <div className="h-1 w-full overflow-hidden rounded-full bg-ink-950/8">
                  <div className="h-full rounded-full bg-gold-500" style={{ width: `${b.progress_percent}%` }} />
                </div>
                <p className="mt-0.5 text-[9px] text-ink-400">
                  {b.progress_current}/{b.criteria_value}
                </p>
              </div>
            )}

            {earned && <p className="text-[9px] font-medium uppercase tracking-wide text-gold-600">Unlocked</p>}
          </div>
        );
      })}
    </div>
  );
}

export default function BadgeGrid({ badges, earnedIds = [] }) {
  return (
    <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
      {badges.map((b) => {
        const earned = earnedIds.includes(b.id);
        return (
          <div
            key={b.id}
            title={b.description}
            className={`flex flex-col items-center gap-1.5 rounded-card border p-3 text-center ${
              earned ? "border-gold-500/50 bg-gold-400/10" : "border-ink-950/8 bg-white/40 opacity-50"
            }`}
          >
            <div
              className={`grid h-12 w-12 place-items-center rounded-full text-lg ${
                earned ? "bg-gold-500 text-ink-950" : "bg-ink-950/10 text-ink-300"
              }`}
            >
              {b.icon ?? "★"}
            </div>
            <p className="text-[11px] font-medium leading-tight text-ink-900">{b.name}</p>
          </div>
        );
      })}
    </div>
  );
}

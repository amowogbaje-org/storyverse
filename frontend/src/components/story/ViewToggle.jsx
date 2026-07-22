export default function ViewToggle({ view, onChange }) {
  return (
    <div className="inline-flex rounded-full border border-ink-950/15 bg-white/60 p-0.5">
      {["grid", "list"].map((v) => (
        <button
          key={v}
          onClick={() => onChange(v)}
          aria-pressed={view === v}
          aria-label={`${v} view`}
          className={`rounded-full px-3 py-1 text-xs font-medium transition ${
            view === v ? "bg-ink-950 text-parchment-50" : "text-ink-500 hover:text-ink-900"
          }`}
        >
          {v === "grid" ? "Grid" : "List"}
        </button>
      ))}
    </div>
  );
}

export default function EmptyState({ title, hint, action }) {
  return (
    <div className="rounded-card border border-dashed border-ink-300/40 bg-white/40 px-6 py-14 text-center">
      <p className="font-display text-lg text-ink-900">{title}</p>
      {hint && <p className="mt-1 text-sm text-ink-500">{hint}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}

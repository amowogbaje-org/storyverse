export default function StatCard({ label, value, sub }) {
  return (
    <div className="rounded-card border border-ink-950/10 bg-white/60 p-4">
      <p className="text-xs font-medium text-ink-500">{label}</p>
      <p className="mt-1 font-display text-2xl font-semibold text-ink-950">{value}</p>
      {sub && <p className="mt-0.5 text-xs text-ink-500">{sub}</p>}
    </div>
  );
}

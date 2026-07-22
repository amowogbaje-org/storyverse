export default function PlanCard({ plan, onSelect, selected }) {
  return (
    <div
      className={`flex flex-col rounded-card border p-5 ${
        selected ? "border-gold-500 bg-gold-400/10" : "border-ink-950/10 bg-white/60"
      }`}
    >
      <p className="font-display text-lg text-ink-950">{plan.name}</p>
      <p className="mt-1 text-2xl font-semibold text-ink-950">
        {plan.currency_symbol}{plan.price}
        <span className="text-sm font-normal text-ink-500">/{plan.interval}</span>
      </p>
      <p className="mt-1 text-xs text-ink-500">Billed in {plan.currency_code} · priced for your region</p>
      <ul className="mt-4 space-y-1.5 text-sm text-ink-700">
        {(plan.features ?? ["Unlimited access to every episode", "No ads", "Early access to new releases"]).map((f) => (
          <li key={f} className="flex items-center gap-2">
            <span className="text-teal-700">✓</span> {f}
          </li>
        ))}
      </ul>
      <button
        onClick={() => onSelect(plan)}
        className="mt-5 rounded-full bg-ink-950 px-4 py-2 text-sm font-medium text-parchment-50 hover:bg-ink-900"
      >
        Choose {plan.name}
      </button>
    </div>
  );
}

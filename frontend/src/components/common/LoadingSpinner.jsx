export default function LoadingSpinner({ label = "Loading" }) {
  return (
    <div className="flex items-center justify-center gap-2 py-10 text-ink-500" role="status">
      <span className="h-4 w-4 animate-spin rounded-full border-2 border-ink-300 border-t-gold-500" />
      <span className="text-sm">{label}…</span>
    </div>
  );
}

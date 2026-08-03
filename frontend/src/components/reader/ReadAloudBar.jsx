export default function ReadAloudBar({ supported, speaking, paused, onPlay, onPause, onStop, autoAdvance, onToggleAutoAdvance, hasNext }) {
  if (!supported) return null;

  return (
    <div className="mt-4 flex flex-wrap items-center gap-3 rounded-card border border-ink-950/10 bg-white/40 p-3">
      {!speaking ? (
        <button
          type="button"
          onClick={onPlay}
          className="rounded-full bg-ink-950 px-4 py-1.5 text-sm font-medium text-parchment-50"
        >
          {paused ? "▶ Resume" : "▶ Read aloud"}
        </button>
      ) : (
        <button
          type="button"
          onClick={onPause}
          className="rounded-full border border-ink-950/15 px-4 py-1.5 text-sm font-medium text-ink-700"
        >
          ⏸ Pause
        </button>
      )}
      {(speaking || paused) && (
        <button type="button" onClick={onStop} className="text-sm text-ink-500 hover:underline">
          Stop
        </button>
      )}
      {hasNext && (
        <label className="ml-auto flex items-center gap-2 text-xs text-ink-600">
          <input type="checkbox" checked={autoAdvance} onChange={onToggleAutoAdvance} className="accent-gold-500" />
          Auto-play next episode
        </label>
      )}
    </div>
  );
}

import { useAppUpdatePrompt } from "../../hooks/useAppUpdatePrompt";

/**
 * Deliberately not dismissible without reloading (unlike InstallPrompt) -
 * this isn't a "maybe later" suggestion. Once a new service worker has taken
 * over the tab, this tab's own in-progress mutations (a login, a progress
 * save) are already going through the NEW backend contract; staying on the
 * old JS bundle indefinitely is the actual bug this exists to prevent, so
 * the only real affordance is "reload now" - a person mid-paragraph can
 * still ignore the banner and finish reading before tapping it, they just
 * can't make it go away without eventually doing so.
 */
export default function UpdateBanner() {
  const { updateAvailable, applyUpdate } = useAppUpdatePrompt();

  if (!updateAvailable) return null;

  return (
    <div className="fixed inset-x-3 top-3 z-50 flex items-center gap-3 rounded-card border border-ink-950/10 bg-white p-3 shadow-card sm:inset-x-auto sm:left-1/2 sm:w-96 sm:-translate-x-1/2">
      <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-teal-600 text-white">↻</span>
      <div className="min-w-0 flex-1">
        <p className="text-sm font-medium text-ink-950">A new version is available</p>
        <p className="text-xs text-ink-500">Reload to get the latest update.</p>
      </div>
      <button
        onClick={applyUpdate}
        className="shrink-0 rounded-full bg-ink-950 px-3 py-1.5 text-xs font-medium text-parchment-50"
      >
        Reload
      </button>
    </div>
  );
}

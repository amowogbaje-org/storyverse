import { useEffect, useState } from "react";

const DISMISSED_KEY = "install_prompt_dismissed_at";
const DISMISS_SNOOZE_DAYS = 14;

function isStandalone() {
  return window.matchMedia?.("(display-mode: standalone)").matches || window.navigator.standalone === true;
}

function isIos() {
  return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
}

function recentlyDismissed() {
  const at = Number(window.localStorage.getItem(DISMISSED_KEY) || 0);
  return at && Date.now() - at < DISMISS_SNOOZE_DAYS * 24 * 60 * 60 * 1000;
}

export default function InstallPrompt() {
  const [deferredPrompt, setDeferredPrompt] = useState(null);
  const [showIosHint, setShowIosHint] = useState(false);
  const [dismissed, setDismissed] = useState(recentlyDismissed());

  useEffect(() => {
    if (isStandalone() || recentlyDismissed()) return;

    function onBeforeInstallPrompt(e) {
      // Suppress the browser's own mini-infobar so we control exactly when/how
      // this is offered, then keep the event around to trigger later from our
      // own button - it can only be used once and only in direct response to
      // a user gesture, which is why this can't just fire immediately here.
      e.preventDefault();
      setDeferredPrompt(e);
    }

    window.addEventListener("beforeinstallprompt", onBeforeInstallPrompt);

    // iOS Safari never fires beforeinstallprompt at all - there's no
    // programmatic install API there, only the manual Share > Add to Home
    // Screen flow, so the best this can do is point that out.
    if (isIos()) {
      setShowIosHint(true);
    }

    return () => window.removeEventListener("beforeinstallprompt", onBeforeInstallPrompt);
  }, []);

  function dismiss() {
    window.localStorage.setItem(DISMISSED_KEY, String(Date.now()));
    setDismissed(true);
  }

  async function install() {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    // Whatever they chose, this specific prompt event is now spent either way.
    setDeferredPrompt(null);
  }

  if (dismissed || (!deferredPrompt && !showIosHint)) return null;

  return (
    <div className="fixed inset-x-3 bottom-16 z-40 flex items-center gap-3 rounded-card border border-ink-950/10 bg-white p-3 shadow-card sm:inset-x-auto sm:bottom-4 sm:right-4 sm:w-80 md:bottom-4">
      <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-ink-950 text-parchment-50">📖</span>
      <div className="min-w-0 flex-1">
        <p className="text-sm font-medium text-ink-950">Install Storyverse</p>
        <p className="text-xs text-ink-500">
          {deferredPrompt
            ? "Add it to your home screen for a faster, full-screen reading experience."
            : "Tap the Share button, then \"Add to Home Screen\"."}
        </p>
      </div>
      <div className="flex shrink-0 flex-col gap-1">
        {deferredPrompt && (
          <button onClick={install} className="rounded-full bg-ink-950 px-3 py-1 text-xs font-medium text-parchment-50">
            Install
          </button>
        )}
        <button onClick={dismiss} className="rounded-full px-3 py-1 text-xs text-ink-400 hover:text-ink-700">
          {deferredPrompt ? "Not now" : "Got it"}
        </button>
      </div>
    </div>
  );
}

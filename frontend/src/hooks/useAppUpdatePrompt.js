import { useEffect, useState } from "react";

/**
 * sw.js already calls self.skipWaiting() + self.clients.claim() unconditionally
 * on every install/activate (see src/sw.js), so a new service worker takes
 * over network requests for this tab automatically, with no "waiting" state
 * to manage. What that DOESN'T do is refresh the JS already sitting in the
 * browser's memory for an already-open tab - React, the router, all of it
 * keeps running the old bundle until an actual page reload happens. That's
 * the real gap behind "changes don't show up until incognito": a returning
 * visit that reuses an already-open tab (or one restored by the browser)
 * never reloads on its own.
 *
 * navigator.serviceWorker.oncontrollerchange fires exactly when a new SW
 * takes control - including the very first time a SW is ever installed for
 * this origin, which isn't an "update" and shouldn't prompt anything. The
 * hadControllerAtStart guard is what tells those two cases apart.
 *
 * Browsers only check the SW script for changes automatically on a page
 * navigation - a tab left open for a while (very plausible mid-episode)
 * wouldn't otherwise notice a new deploy at all. The polling below covers
 * that: registration.update() while the tab is visible, roughly every 5
 * minutes, plus once immediately whenever the tab regains focus (covers the
 * common case of someone switching back after the app auto-updated in the
 * background elsewhere, or after enough time passed for a deploy to land).
 */
export function useAppUpdatePrompt() {
  const [updateAvailable, setUpdateAvailable] = useState(false);

  useEffect(() => {
    if (!("serviceWorker" in navigator)) return;

    let hadControllerAtStart = !!navigator.serviceWorker.controller;

    function onControllerChange() {
      if (!hadControllerAtStart) {
        hadControllerAtStart = true;
        return;
      }
      setUpdateAvailable(true);
    }

    navigator.serviceWorker.addEventListener("controllerchange", onControllerChange);

    function checkForUpdate() {
      navigator.serviceWorker.getRegistration().then((reg) => reg?.update().catch(() => {}));
    }

    let interval;
    function startPolling() {
      window.clearInterval(interval);
      interval = window.setInterval(checkForUpdate, 5 * 60 * 1000);
    }

    function onVisibilityChange() {
      if (document.visibilityState !== "visible") {
        window.clearInterval(interval);
        return;
      }
      checkForUpdate();
      startPolling();
    }

    document.addEventListener("visibilitychange", onVisibilityChange);
    if (document.visibilityState === "visible") startPolling();

    return () => {
      navigator.serviceWorker.removeEventListener("controllerchange", onControllerChange);
      document.removeEventListener("visibilitychange", onVisibilityChange);
      window.clearInterval(interval);
    };
  }, []);

  return {
    updateAvailable,
    // A plain reload is enough - the new SW is already controlling the tab
    // by the time updateAvailable flips true, so this reload is guaranteed
    // to pick up the new bundle rather than racing an in-progress install.
    applyUpdate: () => window.location.reload(),
  };
}

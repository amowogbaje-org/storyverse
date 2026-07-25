import { useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { registerServiceWorker } from "../utils/push";

/**
 * Registers the service worker on every load (silent - no permission prompt,
 * that only happens when the person explicitly opts in via the settings
 * toggle) and wires up notificationclick's postMessage so tapping a push
 * notification that reuses an already-open tab does an SPA navigation
 * instead of leaving the tab sitting on whatever page it happened to be on.
 */
export function usePushSetup() {
  const navigate = useNavigate();

  useEffect(() => {
    if (!("serviceWorker" in navigator)) return;

    registerServiceWorker().catch(() => {});

    function onMessage(event) {
      if (event.data?.type === "notification-click" && event.data.url) {
        navigate(event.data.url);
      }
    }

    navigator.serviceWorker.addEventListener("message", onMessage);
    return () => navigator.serviceWorker.removeEventListener("message", onMessage);
  }, [navigate]);
}

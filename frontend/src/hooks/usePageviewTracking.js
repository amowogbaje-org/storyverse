import { useEffect } from "react";
import { useLocation } from "react-router-dom";
import api from "../api/client";

/**
 * Fires a fire-and-forget pageview beacon on every route change, for the
 * "visits" metric in the admin analytics dashboard. Never blocks rendering
 * and silently ignores failures (analytics should never break the app).
 */
export function usePageviewTracking() {
  const location = useLocation();

  useEffect(() => {
    api.post("/analytics/pageview", { path: location.pathname }).catch(() => {});
  }, [location.pathname]);
}

/**
 * Fixes the "click a story/episode link → blank spinner for a beat" feel on
 * this app's most-clicked links (a story card, an episode row). The cause
 * isn't the data fetch itself - it's that it doesn't even START until AFTER
 * React Router resolves the route, which for a lazy-loaded page (see
 * App.jsx's React.lazy() routes) means waiting on a JS chunk request first.
 * That's a real, visible wait added on top of the API call, purely from
 * sequencing: chunk fetch, then parse/execute, then the component mounts,
 * then ITS effect finally fires the actual API request.
 *
 * This hook collapses that into one head start: on hover (desktop) or
 * touchstart (mobile, which fires ~100-300ms before the tap actually
 * navigates), it kicks off the route's dynamic import() and a React Query
 * prefetch at the same time, so by the time the click lands, both are
 * already in flight or done. Nothing here blocks or delays the click itself
 * - both are fire-and-forget.
 */
import { useCallback, useRef } from "react";

export function usePrefetchOnIntent({ importChunk, prefetch, delay = 60 } = {}) {
  const timer = useRef(null);
  const started = useRef(false);

  const start = useCallback(() => {
    if (started.current) return;
    started.current = true;
    importChunk?.().catch(() => {}); // a lazy route's own chunk - failures here just mean no head start, the click still works
    prefetch?.().catch(() => {});
  }, [importChunk, prefetch]);

  const onPointerEnter = useCallback(() => {
    // Debounced slightly so quickly skimming past many cards/rows (common on
    // a browse grid) doesn't fire a prefetch - and its chunk + API request -
    // for every single one the cursor happens to cross.
    timer.current = window.setTimeout(start, delay);
  }, [start, delay]);

  const onPointerLeave = useCallback(() => {
    window.clearTimeout(timer.current);
  }, []);

  return { onMouseEnter: onPointerEnter, onMouseLeave: onPointerLeave, onTouchStart: start, onFocus: start };
}

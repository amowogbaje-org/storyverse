import { useCallback, useEffect, useRef, useState } from "react";

function ChevronIcon({ direction }) {
  return (
    <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2">
      <path
        d={direction === "left" ? "M15 18l-6-6 6-6" : "M9 6l6 6-6 6"}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

/**
 * A real swipeable, snapping horizontal carousel - CSS scroll-snap under the
 * hood, so touch/trackpad swiping is native and always lands cleanly on an
 * item, plus desktop arrow buttons and page-dot indicators layered on top.
 * A plain `overflow-x-auto` row has neither: content can stop mid-item, and
 * there's no way to page through it without a finger/trackpad.
 *
 * Usage: wrap the same children you'd have put in a scrolling flex row.
 * Each direct child should keep its own `shrink-0` sizing class - this
 * component only adds `snap-start` scroll-snap behavior on top.
 */
export default function Swiper({ children, className = "" }) {
  const trackRef = useRef(null);
  const [page, setPage] = useState(0);
  const [pageCount, setPageCount] = useState(1);

  const recompute = useCallback(() => {
    const el = trackRef.current;
    if (!el || el.clientWidth === 0) return;
    setPageCount(Math.max(1, Math.round(el.scrollWidth / el.clientWidth)));
    setPage(Math.round(el.scrollLeft / el.clientWidth));
  }, []);

  useEffect(() => {
    recompute();
    const el = trackRef.current;
    if (!el) return undefined;

    // rAF-throttled: scroll fires continuously during a swipe, and we only
    // need the settled position often enough to keep dots/arrows in sync.
    let raf = null;
    const onScroll = () => {
      if (raf) return;
      raf = requestAnimationFrame(() => {
        recompute();
        raf = null;
      });
    };
    el.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", recompute);
    return () => {
      el.removeEventListener("scroll", onScroll);
      window.removeEventListener("resize", recompute);
      if (raf) cancelAnimationFrame(raf);
    };
  }, [recompute]);

  function goTo(nextPage) {
    const el = trackRef.current;
    if (!el) return;
    const clamped = Math.max(0, Math.min(pageCount - 1, nextPage));
    el.scrollTo({ left: clamped * el.clientWidth, behavior: "smooth" });
  }

  return (
    <div className={`relative ${className}`}>
      <div
        ref={trackRef}
        className="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-1 [-ms-overflow-style:none] [scrollbar-width:none] sm:mx-0 sm:gap-4 sm:px-0 [&::-webkit-scrollbar]:hidden"
      >
        {children}
      </div>

      {pageCount > 1 && (
        <>
          {/* Arrows: desktop-only affordance. Touch devices already swipe
              natively, and showing arrows there too just adds visual noise
              over a gesture people already know. */}
          <button
            type="button"
            onClick={() => goTo(page - 1)}
            disabled={page === 0}
            aria-label="Previous"
            className="absolute left-0 top-[38%] hidden -translate-x-1/2 -translate-y-1/2 rounded-full border border-ink-950/10 bg-white p-1.5 text-ink-700 shadow-card transition disabled:pointer-events-none disabled:opacity-0 sm:flex sm:items-center sm:justify-center"
          >
            <ChevronIcon direction="left" />
          </button>
          <button
            type="button"
            onClick={() => goTo(page + 1)}
            disabled={page === pageCount - 1}
            aria-label="Next"
            className="absolute right-0 top-[38%] hidden translate-x-1/2 -translate-y-1/2 rounded-full border border-ink-950/10 bg-white p-1.5 text-ink-700 shadow-card transition disabled:pointer-events-none disabled:opacity-0 sm:flex sm:items-center sm:justify-center"
          >
            <ChevronIcon direction="right" />
          </button>

          <div className="mt-2 flex justify-center gap-1.5">
            {Array.from({ length: pageCount }).map((_, i) => (
              <button
                key={i}
                type="button"
                aria-label={`Go to page ${i + 1}`}
                onClick={() => goTo(i)}
                className={`h-1.5 rounded-full transition-all ${
                  i === page ? "w-4 bg-ink-950" : "w-1.5 bg-ink-950/20"
                }`}
              />
            ))}
          </div>
        </>
      )}
    </div>
  );
}

import { useEffect, useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";

export default function WelcomeBanner() {
  const location = useLocation();
  const navigate = useNavigate();
  const [show, setShow] = useState(Boolean(location.state?.welcome));

  useEffect(() => {
    if (location.state?.welcome) {
      // Clear the nav state so a refresh or back-navigation doesn't re-show it.
      navigate(location.pathname, { replace: true, state: {} });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  if (!show) return null;

  return (
    <div className="mb-6 flex items-center justify-between gap-3 rounded-card border border-gold-500/40 bg-gold-400/10 px-4 py-3 text-sm text-ink-900">
      <span>🎉 Welcome to Storyverse! Your account is verified and ready to go — start reading and earning badges.</span>
      <button
        onClick={() => setShow(false)}
        className="shrink-0 text-ink-500 hover:text-ink-900"
        aria-label="Dismiss welcome message"
      >
        ✕
      </button>
    </div>
  );
}

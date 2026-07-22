import { useEffect, useRef, useState } from "react";
import { useAuth } from "../../context/AuthContext";

const CLIENT_ID = import.meta.env.VITE_GOOGLE_CLIENT_ID;

/**
 * Renders Google's own "Sign in with Google" button via the Google Identity
 * Services script (loaded in index.html). Google draws the button itself
 * into our div — we just hand it a client ID and a callback for the
 * resulting ID token, which we forward to the backend for verification.
 */
export default function GoogleButton({ onError, label = "signin_with" }) {
  const { loginWithGoogle } = useAuth();
  const divRef = useRef(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    if (!CLIENT_ID) return;

    let cancelled = false;

    function init() {
      if (cancelled || !window.google?.accounts?.id || !divRef.current) return;

      window.google.accounts.id.initialize({
        client_id: CLIENT_ID,
        callback: async ({ credential }) => {
          try {
            await loginWithGoogle(credential);
          } catch (err) {
            onError?.(err.response?.data?.error?.message || "Google sign-in failed. Please try again.");
          }
        },
      });

      window.google.accounts.id.renderButton(divRef.current, {
        type: "standard",
        theme: "outline",
        size: "large",
        text: label,
        shape: "pill",
        width: 320,
      });

      setReady(true);
    }

    if (window.google?.accounts?.id) {
      init();
    } else {
      // The GSI script (accounts.google.com/gsi/client) is loaded async in index.html;
      // poll briefly in case this component mounts before it's ready.
      const interval = setInterval(() => {
        if (window.google?.accounts?.id) {
          clearInterval(interval);
          init();
        }
      }, 200);
      const timeout = setTimeout(() => clearInterval(interval), 8000);
      return () => {
        cancelled = true;
        clearInterval(interval);
        clearTimeout(timeout);
      };
    }
  }, [loginWithGoogle, onError, label]);

  if (!CLIENT_ID) return null;

  return (
    <div className="flex justify-center">
      <div ref={divRef} />
      {!ready && <span className="text-xs text-ink-500">Loading Google sign-in…</span>}
    </div>
  );
}

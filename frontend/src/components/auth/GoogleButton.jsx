import { useEffect, useRef, useState } from "react";
import { useAuth } from "../../context/AuthContext";

const CLIENT_ID = import.meta.env.VITE_GOOGLE_CLIENT_ID;

/**
 * Renders Google's own "Sign in with Google" button via the Google Identity
 * Services script. Google draws the button itself into our div — we just
 * hand it a client ID and a callback for the resulting ID token, which we
 * forward to the backend for verification.
 *
 * The script is loaded here, on demand, rather than as a static <script> tag
 * in index.html: this component only ever renders on /login and /register,
 * but a static tag in index.html downloaded and executed Google's script on
 * every single page - including every episode read, where it's never used.
 * loadGsiScript() below dedupes so mounting this twice (e.g. both
 * LoginPage's inline form and a modal) doesn't insert the tag more than once.
 */
let gsiScriptPromise = null;
function loadGsiScript() {
  if (window.google?.accounts?.id) return Promise.resolve();
  if (gsiScriptPromise) return gsiScriptPromise;

  gsiScriptPromise = new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = "https://accounts.google.com/gsi/client";
    script.async = true;
    script.defer = true;
    script.onload = resolve;
    script.onerror = () => {
      gsiScriptPromise = null; // allow a retry on next mount if the network hiccuped
      reject(new Error("Failed to load Google Identity Services"));
    };
    document.head.appendChild(script);
  });

  return gsiScriptPromise;
}

export default function GoogleButton({ onError, onSuccess, label = "signin_with" }) {
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
            const data = await loginWithGoogle(credential);
            // Unlike the native email/password form, nothing here used to
            // navigate away on success — the user stayed on /login or
            // /register even though they were now signed in. Callers pass
            // onSuccess to redirect, same as the native submit() handlers do.
            onSuccess?.(data);
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

    loadGsiScript()
      .then(() => { if (!cancelled) init(); })
      .catch(() => { if (!cancelled) onError?.("Couldn't load Google sign-in. Please try again."); });

    return () => {
      cancelled = true;
    };
  }, [loginWithGoogle, onError, onSuccess, label]);

  if (!CLIENT_ID) return null;

  return (
    <div className="flex justify-center">
      <div ref={divRef} />
      {!ready && <span className="text-xs text-ink-500">Loading Google sign-in…</span>}
    </div>
  );
}

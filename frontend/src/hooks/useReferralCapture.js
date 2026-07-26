import { useEffect } from "react";
import { useLocation } from "react-router-dom";

const STORAGE_KEY = "referral_code";

/**
 * Someone can land on any page via a shared referral link (a story page, the
 * homepage, wherever) - not just /register - so this runs at the app root and
 * captures ?ref=CODE the moment it shows up in the URL, persisting it so it's
 * still there whenever they actually get around to signing up. Never
 * overwrites an already-stored code with an absent one - a returning visitor
 * without ?ref= in the URL shouldn't lose their earlier attribution.
 */
export function useReferralCapture() {
  const location = useLocation();

  useEffect(() => {
    const ref = new URLSearchParams(location.search).get("ref");
    if (ref) {
      window.localStorage.setItem(STORAGE_KEY, ref);
    }
  }, [location.search]);
}

export function getStoredReferralCode() {
  return window.localStorage.getItem(STORAGE_KEY);
}

export function clearStoredReferralCode() {
  window.localStorage.removeItem(STORAGE_KEY);
}

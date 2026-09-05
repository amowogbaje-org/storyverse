import { createContext, useContext, useEffect, useState, useCallback } from "react";
import api, { getToken, setToken, getRefreshToken, setRefreshToken, clearSession, registerUnauthorizedHandler } from "../api/client";
import { getStoredReferralCode, clearStoredReferralCode } from "../hooks/useReferralCapture";

const AuthContext = createContext(null);

// Every endpoint that signs someone in now returns both an access token
// ("token") and a refresh token ("refresh_token") - see AuthController's
// issueTokens(). Centralized here so all four call sites below persist both
// the same way rather than repeating the data.data-vs-data fallback dance.
function persistTokens(data) {
  const payload = data.data ?? data;
  setToken(payload.token);
  if (payload.refresh_token) setRefreshToken(payload.refresh_token);
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  const fetchMe = useCallback(async () => {
    if (!getToken()) {
      setUser(null);
      setLoading(false);
      return;
    }
    try {
      const { data } = await api.get("/me");
      setUser(data.data ?? data);
    } catch {
      setToken(null);
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchMe();
    registerUnauthorizedHandler(() => setUser(null));
  }, [fetchMe]);

  // Best-effort locale detection so registration can pre-fill currency/country,
  // matching the backend's GeoDetectionService fallback chain.
  const browserLocale = typeof navigator !== "undefined" ? navigator.language : "en-US";

  // NOTE: wrapped in useCallback so identity stays stable across re-renders.
  // Consumers like GoogleButton depend on `loginWithGoogle` inside a
  // useEffect — without useCallback, a brand new function was created on
  // every AuthProvider render, which re-triggered that effect and
  // re-initialized the Google Identity Services button on every unrelated
  // re-render. That was the source of the Google sign-in "hiccups".
  const login = useCallback(async (email, password) => {
    const { data } = await api.post("/auth/login", { email, password });
    persistTokens(data);
    await fetchMe();
    return data;
  }, [fetchMe]);

  const register = useCallback(async (payload) => {
    const { data } = await api.post("/auth/register", {
      ...payload,
      browser_locale: browserLocale,
      referral_code: getStoredReferralCode(),
    });
    // Registration no longer returns a token directly — the account is created
    // unverified and an OTP is emailed. The caller (RegisterPage) is expected to
    // show an OTP step and call verifyOtp() to actually get a session.
    return data;
  }, [browserLocale]);

  const verifyOtp = useCallback(async ({ email, code }) => {
    const { data } = await api.post("/auth/otp/verify", { email, code, referral_code: getStoredReferralCode() });
    if (data.data?.token ?? data.token) {
      persistTokens(data);
      await fetchMe();
      clearStoredReferralCode();
    }
    return data;
  }, [fetchMe]);

  const resendOtp = useCallback(async ({ email, purpose = "verify" }) => {
    const { data } = await api.post("/auth/otp/request", { email, purpose });
    return data;
  }, []);

  const forgotPassword = useCallback(async (email) => {
    const { data } = await api.post("/auth/password/forgot", { email });
    return data;
  }, []);

  const resetPassword = useCallback(async ({ email, code, password, password_confirmation }) => {
    const { data } = await api.post("/auth/password/reset", { email, code, password, password_confirmation });
    if (data.data?.token ?? data.token) {
      persistTokens(data);
      await fetchMe();
    }
    return data;
  }, [fetchMe]);

  const loginWithGoogle = useCallback(async (credential) => {
    const { data } = await api.post("/auth/google", { credential, referral_code: getStoredReferralCode() });
    persistTokens(data);
    await fetchMe();
    clearStoredReferralCode();
    return data;
  }, [fetchMe]);

  const logout = useCallback(async () => {
    try {
      await api.post("/auth/logout", { refresh_token: getRefreshToken() });
    } catch {
      // ignore — we clear client state regardless
    }
    clearSession();
    setUser(null);
  }, []);

  const value = {
    user,
    isAuthenticated: !!user,
    isPremium: !!user?.has_premium_access,
    loading,
    login,
    register,
    verifyOtp,
    resendOtp,
    forgotPassword,
    resetPassword,
    loginWithGoogle,
    logout,
    refresh: fetchMe,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}

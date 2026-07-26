import { createContext, useContext, useEffect, useState, useCallback } from "react";
import api, { getToken, setToken, registerUnauthorizedHandler } from "../api/client";
import { getStoredReferralCode, clearStoredReferralCode } from "../hooks/useReferralCapture";

const AuthContext = createContext(null);

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

  async function login(email, password) {
    const { data } = await api.post("/auth/login", { email, password });
    const token = data.data?.token ?? data.token;
    setToken(token);
    await fetchMe();
    return data;
  }

  async function register(payload) {
    const { data } = await api.post("/auth/register", {
      ...payload,
      browser_locale: browserLocale,
      referral_code: getStoredReferralCode(),
    });
    // Registration no longer returns a token directly — the account is created
    // unverified and an OTP is emailed. The caller (RegisterPage) is expected to
    // show an OTP step and call verifyOtp() to actually get a session.
    return data;
  }

  async function verifyOtp({ email, code }) {
    const { data } = await api.post("/auth/otp/verify", { email, code, referral_code: getStoredReferralCode() });
    const token = data.data?.token ?? data.token;
    if (token) {
      setToken(token);
      await fetchMe();
      clearStoredReferralCode();
    }
    return data;
  }

  async function resendOtp({ email, purpose = "verify" }) {
    const { data } = await api.post("/auth/otp/request", { email, purpose });
    return data;
  }

  async function forgotPassword(email) {
    const { data } = await api.post("/auth/password/forgot", { email });
    return data;
  }

  async function resetPassword({ email, code, password, password_confirmation }) {
    const { data } = await api.post("/auth/password/reset", { email, code, password, password_confirmation });
    const token = data.data?.token ?? data.token;
    if (token) {
      setToken(token);
      await fetchMe();
    }
    return data;
  }

  async function loginWithGoogle(credential) {
    const { data } = await api.post("/auth/google", { credential, referral_code: getStoredReferralCode() });
    const token = data.data?.token ?? data.token;
    setToken(token);
    await fetchMe();
    clearStoredReferralCode();
    return data;
  }

  async function logout() {
    try {
      await api.post("/auth/logout");
    } catch {
      // ignore — we clear client state regardless
    }
    setToken(null);
    setUser(null);
  }

  const value = {
    user,
    isAuthenticated: !!user,
    isPremium: !!user?.has_active_premium_subscription,
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

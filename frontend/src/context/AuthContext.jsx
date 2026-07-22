import { createContext, useContext, useEffect, useState, useCallback } from "react";
import api, { getToken, setToken, registerUnauthorizedHandler } from "../api/client";

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
    });
    const token = data.data?.token ?? data.token;
    if (token) {
      setToken(token);
      await fetchMe();
    }
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

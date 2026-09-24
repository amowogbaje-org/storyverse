import axios from "axios";

const BASE_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api/v1";

export const api = axios.create({
  baseURL: BASE_URL,
  headers: { Accept: "application/json" },
});

const TOKEN_KEY = "storyverse_token";
const REFRESH_TOKEN_KEY = "storyverse_refresh_token";
const SESSION_KEY = "storyverse_session_id";

export function getSessionId() {
  let id = localStorage.getItem(SESSION_KEY);
  if (!id) {
    id = crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
    localStorage.setItem(SESSION_KEY, id);
  }
  return id;
}

export function getToken() {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
  if (token) localStorage.setItem(TOKEN_KEY, token);
  else localStorage.removeItem(TOKEN_KEY);
}

export function getRefreshToken() {
  return localStorage.getItem(REFRESH_TOKEN_KEY);
}

export function setRefreshToken(token) {
  if (token) localStorage.setItem(REFRESH_TOKEN_KEY, token);
  else localStorage.removeItem(REFRESH_TOKEN_KEY);
}

/** The access and refresh tokens only ever make sense as a pair - always clear them together. */
export function clearSession() {
  setToken(null);
  setRefreshToken(null);
}

api.interceptors.request.use((config) => {
  const token = getToken();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  config.headers["X-Session-Id"] = getSessionId();
  return config;
});

let onUnauthorized = null;
export function registerUnauthorizedHandler(fn) {
  onUnauthorized = fn;
}

// Several requests can all 401 back-to-back the instant an access token
// expires (e.g. a page firing off three queries at once). Without this,
// each one would kick off its own /auth/refresh call and race to store
// tokens. Every 401 that arrives while a refresh is already in flight just
// awaits this same promise instead of starting a redundant one.
let inFlightRefresh = null;

function refreshAccessToken() {
  const refreshToken = getRefreshToken();
  if (!refreshToken) return Promise.resolve(null);

  if (!inFlightRefresh) {
    // Plain axios, not the `api` instance - a request through `api` would
    // re-enter this same response interceptor if it ever 401'd.
    inFlightRefresh = axios
      .post(`${BASE_URL}/auth/refresh`, { refresh_token: refreshToken })
      .then(({ data }) => {
        const payload = data.data ?? data;
        setToken(payload.token);
        setRefreshToken(payload.refresh_token);
        return payload.token;
      })
      .catch(() => {
        clearSession();
        return null;
      })
      .finally(() => {
        inFlightRefresh = null;
      });
  }

  return inFlightRefresh;
}

// Endpoints whose own 401s mean "wrong credentials" / "invalid code" / "this
// refresh token is no longer valid" - never a sign the access token merely
// expired, so never worth retrying via refresh and never worth treating as
// a forced logout.
const AUTH_ENTRY_POINTS = ["/auth/login", "/auth/register", "/auth/google", "/auth/otp/verify", "/auth/password/reset", "/auth/refresh"];

api.interceptors.response.use(
  (res) => res,
  async (error) => {
    const { config, response } = error;

    if (response?.status !== 401 || !config) {
      return Promise.reject(error);
    }

    if (AUTH_ENTRY_POINTS.some((path) => (config.url || "").includes(path))) {
      return Promise.reject(error);
    }

    if (!config._retriedAfterRefresh) {
      config._retriedAfterRefresh = true;
      const newToken = await refreshAccessToken();
      if (newToken) {
        config.headers.Authorization = `Bearer ${newToken}`;
        return api(config);
      }
    }

    // Either there was no refresh token to try, or refreshing itself failed
    // (expired/revoked) - the session is genuinely over.
    clearSession();
    onUnauthorized?.();
    return Promise.reject(error);
  }
);

export default api;

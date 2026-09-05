import { createContext, useContext, useEffect, useMemo, useState } from "react";

const ThemeContext = createContext(null);
const STORAGE_KEY = "storyverse:theme";

// --- Dark theme kill switch -------------------------------------------------
// Dark mode is intentionally disabled site-wide: the toggle and the settings
// option have both been removed from the UI, and the app is pinned to light
// regardless of stored preference or OS setting.
//
// To bring dark mode back, flip this single boolean to `true`. Everything
// else in this file (storage, OS-preference syncing, setTheme/toggleTheme)
// is untouched and will resume working exactly as before - you'll just also
// want to re-add <ThemeToggle /> / <ThemeSettings /> wherever they were
// removed from (see Navbar.jsx and ProfilePage.jsx).
const DARK_THEME_ENABLED = false;
// -----------------------------------------------------------------------------

function getInitialTheme() {
  if (!DARK_THEME_ENABLED) return "light";
  const stored = typeof window !== "undefined" ? window.localStorage.getItem(STORAGE_KEY) : null;
  if (stored === "light" || stored === "dark") return stored;
  if (typeof window !== "undefined" && window.matchMedia?.("(prefers-color-scheme: dark)").matches) {
    return "dark";
  }
  return "light";
}

export function ThemeProvider({ children }) {
  const [theme, setTheme] = useState(getInitialTheme);

  useEffect(() => {
    const root = document.documentElement;
    root.classList.toggle("dark", theme === "dark");
    root.style.colorScheme = theme;
    window.localStorage.setItem(STORAGE_KEY, theme);
  }, [theme]);

  // If the person never explicitly chose a theme, keep following the OS
  // setting live. The moment they do choose (setTheme below), the stored
  // value takes over and this listener's writes are just overwritten again
  // on next render - harmless, but simplest to just let it run.
  useEffect(() => {
    if (!DARK_THEME_ENABLED) return;
    const media = window.matchMedia?.("(prefers-color-scheme: dark)");
    if (!media) return;
    function onChange(e) {
      if (!window.localStorage.getItem(STORAGE_KEY + ":explicit")) {
        setTheme(e.matches ? "dark" : "light");
      }
    }
    media.addEventListener?.("change", onChange);
    return () => media.removeEventListener?.("change", onChange);
  }, []);

  const value = useMemo(
    () => ({
      theme,
      setTheme: (next) => {
        if (!DARK_THEME_ENABLED) return;
        window.localStorage.setItem(STORAGE_KEY + ":explicit", "1");
        setTheme(next);
      },
      toggleTheme: () => {
        if (!DARK_THEME_ENABLED) return;
        window.localStorage.setItem(STORAGE_KEY + ":explicit", "1");
        setTheme((t) => (t === "dark" ? "light" : "dark"));
      },
    }),
    [theme]
  );

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme() {
  const ctx = useContext(ThemeContext);
  if (!ctx) throw new Error("useTheme must be used within a ThemeProvider");
  return ctx;
}

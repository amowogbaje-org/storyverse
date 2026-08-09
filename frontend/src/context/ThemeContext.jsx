import { createContext, useContext, useEffect, useMemo, useState } from "react";

const ThemeContext = createContext(null);
const STORAGE_KEY = "storyverse:theme";

function getInitialTheme() {
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
        window.localStorage.setItem(STORAGE_KEY + ":explicit", "1");
        setTheme(next);
      },
      toggleTheme: () => {
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

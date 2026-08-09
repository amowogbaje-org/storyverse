import React from "react";
import ReactDOM from "react-dom/client";
import { BrowserRouter } from "react-router-dom";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { HelmetProvider } from "react-helmet-async";
import App from "./App.jsx";
import { AuthProvider } from "./context/AuthContext.jsx";
import { ThemeProvider } from "./context/ThemeContext.jsx";
import "./index.css";

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      refetchOnWindowFocus: false,
      retry: 1,
    },
  },
});

// Speeds up the very first API call of the session (DNS + TLS handshake
// happen ahead of time instead of blocking the first request) - done here
// rather than a static <link> in index.html since the API origin is
// env-dependent, not a fixed value the static HTML can hardcode.
try {
  const apiOrigin = new URL(import.meta.env.VITE_API_URL).origin;
  const link = document.createElement("link");
  link.rel = "preconnect";
  link.href = apiOrigin;
  document.head.appendChild(link);
} catch {
  // VITE_API_URL missing/malformed - not worth failing app boot over a perf hint.
}

ReactDOM.createRoot(document.getElementById("root")).render(
  <React.StrictMode>
    <BrowserRouter>
      <HelmetProvider>
        <QueryClientProvider client={queryClient}>
          <ThemeProvider>
            <AuthProvider>
              <App />
            </AuthProvider>
          </ThemeProvider>
        </QueryClientProvider>
      </HelmetProvider>
    </BrowserRouter>
  </React.StrictMode>
);

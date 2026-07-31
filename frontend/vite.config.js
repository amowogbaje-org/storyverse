import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import { VitePWA } from "vite-plugin-pwa";

export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      // We ship our own service worker (src/sw.js) because it also handles
      // web push (notificationclick routing, etc.) - injectManifest bundles
      // that file and injects the precache list into it, rather than
      // generating a whole separate SW from scratch (generateSW mode) that
      // would know nothing about push.
      strategies: "injectManifest",
      srcDir: "src",
      filename: "sw.js",
      injectManifest: {
        // Keep the precache list to app-shell assets - large uploaded content
        // (episode text, cover images) is handled by the runtime caching
        // routes in sw.js instead, not precached up front.
        globPatterns: ["**/*.{js,css,html}"],
      },
      manifest: false, // we hand-wrote public/manifest.webmanifest and link it directly in index.html
      injectRegister: false, // registered manually in src/hooks/usePushSetup.js (also handles push setup)
      devOptions: {
        enabled: false, // avoid SW caching fighting with Vite's dev HMR
      },
    }),
  ],
  server: {
    host: true,
    port: 5173,
  },
  build: {
    // Story text/covers can make some pages heavier than the default 500kb
    // warning threshold expects - route-level code splitting (see App.jsx's
    // React.lazy() usage) keeps any single chunk reasonable; this just stops
    // the build from warning about it.
    chunkSizeWarningLimit: 700,
  },
});

import { precacheAndRoute, cleanupOutdatedCaches } from "workbox-precaching";
import { registerRoute } from "workbox-routing";
import { NetworkFirst, StaleWhileRevalidate, CacheFirst } from "workbox-strategies";
import { ExpirationPlugin } from "workbox-expiration";

// injectManifest (vite-plugin-pwa) replaces this placeholder at build time
// with the list of every hashed build asset - that's what makes the app
// shell (JS/CSS) load instantly and work offline after a first visit.
cleanupOutdatedCaches();
precacheAndRoute(self.__WB_MANIFEST || []);

self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));

// Full-page navigations (typing a URL, refreshing, opening from the home
// screen icon): try the network first so anyone online always gets the
// current app, but fall back to the cached shell within 4s or on total
// failure - the difference between the app still opening offline versus the
// browser's own "no internet" page.
registerRoute(
  ({ request }) => request.mode === "navigate",
  new NetworkFirst({
    cacheName: "pages",
    networkTimeoutSeconds: 4,
    plugins: [new ExpirationPlugin({ maxEntries: 25 })],
  })
);

// Cover images and similar same-origin assets rarely change once uploaded -
// cache-first is safe and means a story you've already opened once shows its
// cover instantly (or offline) from then on.
registerRoute(
  ({ request }) => request.destination === "image",
  new CacheFirst({
    cacheName: "images",
    plugins: [new ExpirationPlugin({ maxEntries: 150, maxAgeSeconds: 30 * 24 * 60 * 60 })],
  })
);

// Read-only API GETs: stale-while-revalidate so a story/list you've already
// viewed renders instantly from cache (even offline) while a fresh copy
// fetches in the background for next time. Deliberately excludes anything
// personal/mutable-feeling (/me, /admin, /notifications) - those should
// always reflect the real current state, never a stale cached one.
registerRoute(
  ({ url, request }) =>
    request.method === "GET" &&
    url.pathname.startsWith("/api/") &&
    !/\/(me|admin|notifications)(\/|$)/.test(url.pathname),
  new StaleWhileRevalidate({
    cacheName: "api-get",
    plugins: [new ExpirationPlugin({ maxEntries: 200, maxAgeSeconds: 60 * 60 })],
  })
);

self.addEventListener("push", (event) => {
  if (!event.data) return;

  let payload;
  try {
    payload = event.data.json();
  } catch {
    payload = { title: "Storyverse", body: event.data.text() };
  }

  const { title, body, url, icon, image } = payload;

  event.waitUntil(
    self.registration.showNotification(title || "Storyverse", {
      body,
      // icon/badge stay the app's own icon (small, always-branded) - a story
      // cover belongs in `image` instead, which browsers render as a large
      // banner within the notification body. Passing a rectangular cover
      // photo as `icon` squeezes it into that small badge-sized slot and
      // loses the app's own branding, which is why these were kept separate
      // even when a notification also carries a cover image.
      icon: icon || "/icons/icon-192.png",
      badge: "/icons/icon-192.png",
      image: image || undefined,
      data: { url: url || "/" },
    })
  );
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const url = event.notification.data?.url || "/";

  event.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((clients) => {
      // Reuse an already-open tab if there is one, rather than piling up new
      // tabs every time someone taps a notification.
      for (const client of clients) {
        if ("focus" in client) {
          client.postMessage({ type: "notification-click", url });
          return client.focus();
        }
      }
      return self.clients.openWindow(url);
    })
  );
});

// Deliberately minimal - this app isn't a full offline-first PWA, this worker
// exists only to receive push events and show/route notifications while the
// tab isn't focused (or isn't open at all).

self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));

self.addEventListener("push", (event) => {
  if (!event.data) return;

  let payload;
  try {
    payload = event.data.json();
  } catch {
    payload = { title: "Storyverse", body: event.data.text() };
  }

  const { title, body, url, icon } = payload;

  event.waitUntil(
    self.registration.showNotification(title || "Storyverse", {
      body,
      icon: icon || "/icon-192.png",
      badge: "/icon-192.png",
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

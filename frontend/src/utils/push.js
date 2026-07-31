import api from "../api/client";

/**
 * @returns {"ok"|"unsupported_browser"|"insecure_context"|"not_configured"}
 *
 * These three failure modes get conflated into one "not supported" message
 * far too often, which is actively misleading: a modern Chrome on a real
 * device almost certainly *does* support the Push API - if this returns
 * anything other than "ok" on a phone/desktop Chrome, it's overwhelmingly
 * more likely to be "not_configured" (VITE_VAPID_PUBLIC_KEY missing from the
 * build, or the frontend just hasn't been rebuilt since it was set) than an
 * actual browser limitation.
 */
export function getPushSupportStatus() {
  // Service workers and the Push API are only exposed in secure contexts
  // (HTTPS, or localhost for local dev) - on plain HTTP, `serviceWorker` and
  // `PushManager` won't exist at all even in a fully capable browser, which
  // looks identical to "unsupported" unless you check this separately.
  if (!window.isSecureContext) {
    return "insecure_context";
  }

  if (!("serviceWorker" in navigator) || !("PushManager" in window)) {
    return "unsupported_browser";
  }

  if (!import.meta.env.VITE_VAPID_PUBLIC_KEY) {
    return "not_configured";
  }

  return "ok";
}

export function isPushSupported() {
  return getPushSupportStatus() === "ok";
}

// Web push subscriptions need the VAPID public key as a raw Uint8Array, but
// it's distributed (and stored in .env) as a URL-safe base64 string - this is
// the standard conversion every web-push tutorial reaches for.
function urlBase64ToUint8Array(base64String) {
  const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
  const rawData = window.atob(base64);
  return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
}

export async function registerServiceWorker() {
  if (!("serviceWorker" in navigator)) return null;
  return navigator.serviceWorker.register("/sw.js");
}

/** Prompts for notification permission (if not already decided) and subscribes. Throws if denied. */
export async function subscribeToPush() {
  const registration = (await navigator.serviceWorker.getRegistration()) || (await registerServiceWorker());

  const permission = await Notification.requestPermission();
  if (permission !== "granted") {
    throw new Error("Notification permission was not granted.");
  }

  const subscription = await registration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(import.meta.env.VITE_VAPID_PUBLIC_KEY),
  });

  await api.post("/me/push-subscriptions", subscription.toJSON());
  return subscription;
}

export async function unsubscribeFromPush() {
  const registration = await navigator.serviceWorker.getRegistration();
  const subscription = await registration?.pushManager.getSubscription();

  if (!subscription) return;

  await api.delete("/me/push-subscriptions", { data: { endpoint: subscription.endpoint } });
  await subscription.unsubscribe();
}

export async function getCurrentPushSubscription() {
  if (!isPushSupported()) return null;
  const registration = await navigator.serviceWorker.getRegistration();
  return (await registration?.pushManager.getSubscription()) ?? null;
}

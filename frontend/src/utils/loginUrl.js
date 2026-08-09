/** Builds a /login URL that returns the reader to `path` after they sign in. */
export function loginUrl(path) {
  return `/login?next=${encodeURIComponent(path)}`;
}

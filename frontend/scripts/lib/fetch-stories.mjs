// Shared between generate-seo-files.mjs (prebuild: sitemap/robots) and
// prerender-stories.mjs (postbuild: static per-story HTML snapshots) so both
// walk the catalog the same way instead of two slightly-different copies of
// the same pagination/error-handling logic drifting apart over time.
const API_URL = (process.env.VITE_API_URL || "http://localhost:8000/api/v1").replace(/\/$/, "");

/** @returns {Promise<Array<{slug:string, published_at?:string}>>} */
export async function fetchStoryList() {
  const stories = [];
  let cursor = null;

  for (let page = 0; page < 200; page++) {
    const url = new URL(`${API_URL}/stories`);
    url.searchParams.set("sort", "new");
    if (cursor) url.searchParams.set("cursor", cursor);

    let res;
    try {
      res = await fetch(url);
    } catch (err) {
      console.warn(`[seo] Couldn't reach API at ${API_URL} - continuing without story data. (${err.message})`);
      return stories;
    }

    if (!res.ok) {
      console.warn(`[seo] API returned ${res.status} for ${url} - continuing without story data.`);
      return stories;
    }

    const body = await res.json();
    const items = body.data ?? [];
    stories.push(...items.filter((s) => s.slug));

    cursor = body.meta?.cursor ?? null;
    if (!cursor || !body.meta?.has_more) break;
  }

  return stories;
}

/**
 * Full detail for one story, including its episode list - the list endpoint
 * above doesn't carry episodes, so anything needing per-episode URLs (the
 * sitemap, the prerenderer) has to fetch each story individually. Bounded by
 * total story count and only runs at build time, not per visitor.
 */
export async function fetchStoryDetail(slug) {
  try {
    const res = await fetch(`${API_URL}/stories/${encodeURIComponent(slug)}`);
    if (!res.ok) return null;
    const body = await res.json();
    return body.data ?? null;
  } catch (err) {
    console.warn(`[seo] Couldn't fetch detail for "${slug}": ${err.message}`);
    return null;
  }
}

export { API_URL };

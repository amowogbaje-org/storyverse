// Runs before every `npm run build` (see package.json's "prebuild" script).
// Writes public/sitemap.xml and public/robots.txt so they're static files
// served from the same origin as the site - the deliberately simple choice
// for a statically-hosted SPA (Docker/cPanel both just serve a dist/
// folder), versus trying to generate them dynamically at request time from
// somewhere that may be on a different (sub)domain than the pages they list.
//
// Trade-off worth knowing: the sitemap only reflects stories (and their
// episodes) that existed at build time. Re-run this (i.e. redeploy)
// periodically, or wire it into a scheduled CI job, if new content should
// show up in it without a full app deploy.
import fs from "node:fs";
import path from "node:path";
import { fetchStoryList, fetchStoryDetail } from "./lib/fetch-stories.mjs";

const SITE_URL = (process.env.VITE_SITE_URL || "http://localhost:5173").replace(/\/$/, "");

const STATIC_ROUTES = [
  { path: "/", changefreq: "daily", priority: "1.0" },
  { path: "/browse", changefreq: "daily", priority: "0.9" },
  { path: "/badges", changefreq: "weekly", priority: "0.3" },
];

function xmlEscape(value) {
  return String(value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

function urlEntry({ loc, lastmod, changefreq, priority }) {
  return [
    "  <url>",
    `    <loc>${xmlEscape(loc)}</loc>`,
    lastmod ? `    <lastmod>${lastmod}</lastmod>` : null,
    `    <changefreq>${changefreq}</changefreq>`,
    `    <priority>${priority}</priority>`,
    "  </url>",
  ]
    .filter(Boolean)
    .join("\n");
}

async function main() {
  const storyList = await fetchStoryList();

  // Each episode is its own page of unique, indexable content (see
  // EpisodeReaderPage's Seo/structured-data) - it belongs in the sitemap
  // same as the story it's part of, not just the story's own URL.
  const urls = [...STATIC_ROUTES.map((r) => urlEntry({ loc: `${SITE_URL}${r.path}`, changefreq: r.changefreq, priority: r.priority }))];
  let episodeCount = 0;

  for (const story of storyList) {
    urls.push(
      urlEntry({
        loc: `${SITE_URL}/stories/${story.slug}`,
        lastmod: story.published_at ? new Date(story.published_at).toISOString() : undefined,
        changefreq: "weekly",
        priority: "0.8",
      })
    );

    const detail = await fetchStoryDetail(story.slug);
    for (const ep of detail?.episodes ?? []) {
      if (ep.locked) continue; // don't index content a visitor can't actually read
      urls.push(
        urlEntry({
          loc: `${SITE_URL}/stories/${story.slug}/episodes/${ep.episode_number}`,
          changefreq: "monthly",
          priority: "0.6",
        })
      );
      episodeCount++;
    }
  }

  const xml = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.join("\n")}\n</urlset>\n`;

  fs.mkdirSync(path.resolve("public"), { recursive: true });
  fs.writeFileSync(path.resolve("public/sitemap.xml"), xml);
  console.log(`[seo] Wrote public/sitemap.xml (${STATIC_ROUTES.length} static + ${storyList.length} stories + ${episodeCount} episodes)`);

  const robots = `User-agent: *
Allow: /
Disallow: /admin
Disallow: /profile
Disallow: /subscription
Disallow: /login
Disallow: /register
Disallow: /forgot-password
Disallow: /referrals

Sitemap: ${SITE_URL}/sitemap.xml
`;
  fs.writeFileSync(path.resolve("public/robots.txt"), robots);
  console.log("[seo] Wrote public/robots.txt");
}

main();

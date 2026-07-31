// Runs after `vite build` (see package.json's "postbuild" script), using the
// already-built dist/index.html (with its real hashed script/link tags) as a
// template. For every published story and episode, writes a customized copy
// to e.g. dist/stories/{slug}/index.html.
//
// Why this exists at all: react-helmet-async (the <Seo> component) only
// updates <title>/meta tags *after* React mounts and that page's component
// runs. That's invisible to anything that doesn't execute JavaScript -
// which is most social-media link unfurlers (WhatsApp, Twitter/X, iMessage,
// Slack) and some search engines. Without this, sharing a story link
// anywhere shows generic "Storyverse" preview text instead of that story's
// actual title/cover/description - a real problem for an app that just
// built a whole Share feature.
//
// How it still works for real visitors: the existing SPA routing
// (frontend/public/.htaccess's RewriteCond %{REQUEST_FILENAME} !-f) already
// prefers serving a real file over falling back to index.html - so once
// these per-story files exist, a browser requesting /stories/my-story gets
// this prerendered file first. It includes the exact same JS bundle tag as
// index.html, so React still mounts normally and takes over navigation from
// there - this is a one-time static snapshot for the initial load, not real
// SSR/hydration.
import fs from "node:fs";
import path from "node:path";
import { fetchStoryList, fetchStoryDetail } from "./lib/fetch-stories.mjs";

const SITE_URL = (process.env.VITE_SITE_URL || "http://localhost:5173").replace(/\/$/, "");
const DIST_DIR = path.resolve("dist");
const SITE_NAME = "Storyverse";

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function absoluteUrl(image) {
  if (!image) return `${SITE_URL}/icons/icon-512.png`;
  return image.startsWith("http") ? image : `${SITE_URL}${image}`;
}

function seoBlock({ title, description, image, url, type, structuredData }) {
  const fullTitle = `${escapeHtml(title)} · ${SITE_NAME}`;
  const desc = escapeHtml(description || "");
  const img = absoluteUrl(image);

  // data-rh="true" matches the marker react-helmet-async's own client-side
  // commit looks for to recognize "tags I'm responsible for" - without it,
  // when <Seo> (the Helmet component) mounts and renders the exact same
  // tags, it has no way to know these already exist and just appends a
  // second copy of each instead of replacing them.
  const ld = structuredData
    .filter(Boolean)
    .map((data) => `<script type="application/ld+json" data-rh="true">${JSON.stringify(data)}</script>`)
    .join("\n    ");

  return `<title data-rh="true">${fullTitle}</title>
    <meta data-rh="true" name="description" content="${desc}" />
    <link data-rh="true" rel="canonical" href="${url}" />
    <meta data-rh="true" property="og:site_name" content="${SITE_NAME}" />
    <meta data-rh="true" property="og:type" content="${type}" />
    <meta data-rh="true" property="og:title" content="${fullTitle}" />
    <meta data-rh="true" property="og:description" content="${desc}" />
    <meta data-rh="true" property="og:image" content="${img}" />
    <meta data-rh="true" property="og:url" content="${url}" />
    <meta data-rh="true" name="twitter:card" content="summary_large_image" />
    <meta data-rh="true" name="twitter:title" content="${fullTitle}" />
    <meta data-rh="true" name="twitter:description" content="${desc}" />
    <meta data-rh="true" name="twitter:image" content="${img}" />
    ${ld}`;
}

/** Basic readable snapshot for non-JS visitors/crawlers - replaced the instant React mounts for everyone else. */
function contentSnapshot({ heading, description, image, links }) {
  const linksHtml = links
    .map((l) => `<li><a href="${escapeHtml(l.href)}">${escapeHtml(l.label)}</a></li>`)
    .join("\n          ");

  return `<div style="max-width:640px;margin:0 auto;padding:24px;font-family:system-ui,sans-serif;">
        ${image ? `<img src="${escapeHtml(image)}" alt="${escapeHtml(heading)}" style="max-width:220px;border-radius:12px;" />` : ""}
        <h1>${escapeHtml(heading)}</h1>
        <p>${escapeHtml(description || "")}</p>
        <ul>
          ${linksHtml}
        </ul>
      </div>`;
}

function writePage(template, { relativePath, seo, snapshot }) {
  const outPath = path.join(DIST_DIR, relativePath, "index.html");
  const html = template
    .replace(/<!-- SEO:START -->[\s\S]*?<!-- SEO:END -->/, seoBlock(seo))
    .replace(/<!-- APP:START -->[\s\S]*?<!-- APP:END -->/, contentSnapshot(snapshot));

  fs.mkdirSync(path.dirname(outPath), { recursive: true });
  fs.writeFileSync(outPath, html);
}

async function main() {
  if (!fs.existsSync(path.join(DIST_DIR, "index.html"))) {
    console.warn("[prerender] dist/index.html not found - did `vite build` run first? Skipping.");
    return;
  }

  const template = fs.readFileSync(path.join(DIST_DIR, "index.html"), "utf8");

  if (!/<!-- SEO:START -->/.test(template) || !/<!-- APP:START -->/.test(template)) {
    console.warn("[prerender] index.html is missing the SEO:START/APP:START markers - skipping prerender (nothing to replace).");
    return;
  }

  const storyList = await fetchStoryList();
  let storyCount = 0;
  let episodeCount = 0;

  for (const story of storyList) {
    const detail = await fetchStoryDetail(story.slug);
    if (!detail) continue;

    const storyUrl = `${SITE_URL}/stories/${story.slug}`;

    writePage(template, {
      relativePath: `stories/${story.slug}`,
      seo: {
        title: detail.title,
        description: detail.description,
        image: detail.cover_image_url,
        url: storyUrl,
        type: "book",
        structuredData: [
          {
            "@context": "https://schema.org",
            "@type": "Book",
            name: detail.title,
            description: detail.description,
            image: detail.cover_image_url,
            author: detail.author_display_name ? { "@type": "Person", name: detail.author_display_name } : undefined,
            genre: (detail.categories ?? []).map((c) => c.name).join(", "),
          },
          {
            "@context": "https://schema.org",
            "@type": "BreadcrumbList",
            itemListElement: [
              { "@type": "ListItem", position: 1, name: "Home", item: SITE_URL },
              { "@type": "ListItem", position: 2, name: detail.title, item: storyUrl },
            ],
          },
        ],
      },
      snapshot: {
        heading: detail.title,
        description: detail.description,
        image: detail.cover_image_url,
        links: (detail.episodes ?? [])
          .filter((ep) => !ep.locked)
          .map((ep) => ({ href: `/stories/${story.slug}/episodes/${ep.episode_number}`, label: `Episode ${ep.episode_number}: ${ep.title}` })),
      },
    });
    storyCount++;

    for (const ep of detail.episodes ?? []) {
      if (ep.locked) continue;

      const episodeUrl = `${storyUrl}/episodes/${ep.episode_number}`;

      writePage(template, {
        relativePath: `stories/${story.slug}/episodes/${ep.episode_number}`,
        seo: {
          title: `${detail.title} — Episode ${ep.episode_number}: ${ep.title}`,
          description: detail.description,
          image: detail.cover_image_url,
          url: episodeUrl,
          type: "article",
          structuredData: [
            {
              "@context": "https://schema.org",
              "@type": "Chapter",
              isPartOf: { "@type": "Book", name: detail.title },
              name: ep.title,
              position: ep.episode_number,
            },
          ],
        },
        snapshot: {
          heading: `${detail.title} — Episode ${ep.episode_number}: ${ep.title}`,
          description: detail.description,
          image: detail.cover_image_url,
          links: [{ href: `/stories/${story.slug}`, label: `Back to ${detail.title}` }],
        },
      });
      episodeCount++;
    }
  }

  console.log(`[prerender] Wrote ${storyCount} story page(s) and ${episodeCount} episode page(s) into dist/`);
}

main();

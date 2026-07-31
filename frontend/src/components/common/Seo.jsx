import { Helmet } from "react-helmet-async";

const SITE_NAME = "Storyverse";
const SITE_URL = (import.meta.env.VITE_SITE_URL || "").replace(/\/$/, "");
const DEFAULT_IMAGE = "/icons/icon-512.png";

/**
 * Drop this into any page that has something more specific to say than the
 * app-wide defaults in index.html - a story's own title/description/cover
 * instead of the generic Storyverse ones, for example.
 *
 * @param {string} title - shown as "{title} · Storyverse" in the tab and search results
 * @param {string} [description]
 * @param {string} [image] - absolute or root-relative; resolved against VITE_SITE_URL if relative
 * @param {string} [path] - root-relative path (e.g. "/stories/my-story") used for the canonical link + og:url
 * @param {"website"|"article"|"book"} [type]
 * @param {boolean} [noindex] - for pages that exist but shouldn't show up in search results (e.g. checkout)
 * @param {object|object[]} [structuredData] - JSON-LD object(s), rendered as <script type="application/ld+json">
 */
export default function Seo({
  title,
  description,
  image = DEFAULT_IMAGE,
  path,
  type = "website",
  noindex = false,
  structuredData,
}) {
  const fullTitle = title ? `${title} · ${SITE_NAME}` : `${SITE_NAME} — Read stories worth staying up for`;
  const absoluteImage = image?.startsWith("http") ? image : `${SITE_URL}${image}`;
  const canonical = path && SITE_URL ? `${SITE_URL}${path}` : undefined;
  const structuredDataList = structuredData ? (Array.isArray(structuredData) ? structuredData : [structuredData]) : [];

  return (
    <Helmet>
      <title>{fullTitle}</title>
      {description && <meta name="description" content={description} />}
      {noindex && <meta name="robots" content="noindex, nofollow" />}
      {canonical && <link rel="canonical" href={canonical} />}

      <meta property="og:site_name" content={SITE_NAME} />
      <meta property="og:type" content={type} />
      <meta property="og:title" content={fullTitle} />
      {description && <meta property="og:description" content={description} />}
      <meta property="og:image" content={absoluteImage} />
      {canonical && <meta property="og:url" content={canonical} />}

      <meta name="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" content={fullTitle} />
      {description && <meta name="twitter:description" content={description} />}
      <meta name="twitter:image" content={absoluteImage} />

      {structuredDataList.map((data, i) => (
        <script key={i} type="application/ld+json">
          {JSON.stringify(data)}
        </script>
      ))}
    </Helmet>
  );
}

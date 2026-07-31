# SEO Guide — What's Left Outside the Code

The app-side technical SEO work is done: sitemap/robots.txt generation,
per-page meta tags and structured data, static prerendering so social/search
crawlers see real content, PWA installability, and performance work (code
splitting, caching, compression). All of that is necessary, but **none of it
guarantees a #1 ranking for anything** — ranking depends on how many other
sites are competing for the same terms and how established they already are.
That part isn't something code or a checklist controls.

What you *can* control is giving the app a fair, complete shot at ranking
well. This document is that: the steps that happen outside the codebase,
roughly in the order they matter.

---

## 1. Claim your presence with search engines (do this first — free, ~10 minutes)

Nothing below matters if search engines don't know the site exists yet.

- **Google Search Console** ([search.google.com/search-console](https://search.google.com/search-console))
  - Add the property, verify ownership. The verification meta tag is already
    sitting commented-out in `frontend/index.html` (`google-site-verification`)
    - paste in the value Search Console gives you and uncomment it, then redeploy.
  - Submit `https://yourdomain.com/sitemap.xml` under Sitemaps.
  - After a few days, use **URL Inspection** on a handful of key pages (homepage,
    a couple of popular stories) to confirm they're actually indexed, not just submitted.
- **Bing Webmaster Tools** ([bing.com/webmasters](https://www.bing.com/webmasters))
  - Same idea. The `msvalidate.01` meta tag is also already commented out in `index.html`.
  - Bing (and by extension, some of DuckDuckGo's results) is worth the extra
    10 minutes - it's a meaningfully smaller lift than Google for real traffic share.
- Re-check both every few weeks early on - indexing issues (blocked pages,
  soft 404s, mobile usability warnings) show up here before they show up anywhere else.

## 2. Decide what terms you're actually going for

"Storyverse" (or whatever the brand name is) ranking for its own name is
easy and happens almost automatically. Ranking for something broad like
"read fantasy fiction online" is a real fight against sites that have been
indexed for years with huge backlink profiles (Wattpad, Royal Road, etc.).

- Pick specific, realistic phrases rather than the broadest possible term:
  a genre + format combo ("read serialized romance novels free"), or
  whatever genuinely differentiates the platform (the badge/reward system,
  a particular niche genre focus, etc.).
- Every story's own title/description is also a long-tail SEO opportunity -
  a specific, well-written story description can rank for searches about
  *that story's* premise even before the site itself has much authority.
  This is a reason to encourage authors to write real, specific descriptions
  rather than one-line placeholders.

## 3. Backlinks — usually the single biggest lever, and it's entirely off-site

Google weighs how many *other* reputable sites link to yours heavily. A
brand-new site with zero backlinks will struggle to outrank established
competitors no matter how clean the code is underneath it.

Realistic starting points:
- Get listed on fiction/webnovel aggregators and directories relevant to your genre focus.
- Ask authors publishing on the platform to link to their own stories from
  wherever they already have an audience (their own blog, Twitter/X, Discord, etc.) -
  this is the single easiest source of early backlinks, since it doesn't
  depend on convincing a stranger to link to you.
- Relevant subreddits/forums for the genres you host - contribute genuinely
  to the community, don't just drop links; that gets you banned, not ranked.
- Any press or community writeup about the platform's launch that links back.
- A guest post or interview on a site that already covers indie fiction/writing.

## 4. Content signals that come from usage, not setup

- **Publishing cadence.** A site with no new stories in months signals
  staleness to search engines. Regular new content (which also feeds the
  sitemap and the "new releases" recommendation notifications already built)
  is itself an SEO signal, not just a user-facing feature.
- **Original, specific descriptions.** Thin ("A story about love and loss.")
  or copy-pasted descriptions rank worse than a few genuinely distinct
  sentences about what makes that particular story worth reading.
- **Engagement signals.** Time-on-page, low bounce rate, and return visits
  feed into ranking indirectly through Google's own behavioral signals - this
  is part of why the badges, streaks, and re-engagement notifications already
  built matter for SEO too, not just retention.

## 5. Brand/social consistency

Set up a consistent presence on whatever social platforms make sense
(Twitter/X, Instagram, TikTok, whatever your actual audience uses), using
the same name and linking back to the site. This reinforces to search
engines (and to Google's "knowledge panel"-style brand results) that this is
a real, established entity rather than an anonymous domain.

## 6. Verify the technical work actually shows up correctly

Once deployed:
- Paste a story URL into [Google's Rich Results Test](https://search.google.com/test/rich-results)
  to confirm the structured data (Book/Chapter/BreadcrumbList schema) is
  valid and detected.
- Paste a story URL into a WhatsApp/Slack/iMessage conversation to yourself
  and confirm the link preview shows that story's actual title/cover/description,
  not generic site-wide text. This is exactly what the static prerendering
  work was for - if it's not showing correctly, that's the first place to debug.
- Run the homepage and a story page through [PageSpeed Insights](https://pagespeed.web.dev/)
  - Core Web Vitals (loading speed, interactivity, visual stability) are a
    confirmed Google ranking factor, and this tells you concretely where you stand.

## 7. Patience

Even with everything above done well, a new site typically takes **weeks to
months** to get meaningfully crawled, indexed, and ranked - Google's trust/
authority signals for a domain accumulate over time and aren't instant no
matter how correct the setup is. Don't read "not ranking yet" a week after
launch as something being broken.

---

## If you had to do only three things

1. **Search Console + Bing Webmaster Tools registration** (today, free, 10 minutes).
2. **Backlinks** - specifically, get your authors to link to their own stories
   from wherever they already have an audience. This is the highest-leverage
   thing on this list and the only one that's genuinely free and immediately
   actionable.
3. **Keep publishing.** A content site's SEO compounds with volume and time
   far more than with any one-time technical fix.

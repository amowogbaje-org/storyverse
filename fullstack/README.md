# Storyverse - fullstack (Laravel Blade + Alpine)

A single server-rendered Laravel app, replacing the `backend/` (API) +
`frontend/` (React SPA) split. Same database, same models, same core
business logic - just one app instead of two, so there's one less network
round-trip (browser -> API -> DB) on every page, no SPA JS bundle to
download/parse before anything is visible, and one deploy instead of two.

`backend/` and `frontend/` are untouched and still exist in this repo - they
just no longer deploy anywhere (see `.github/workflows/`). Safe to keep
around for reference while this is stabilizing, delete later.

## What's built

- Reading: home page (new releases + popular, cached), browse/search with
  category/genre/sort filters, story detail page, episode reader with the
  same guest-preview-limit / premium-purchase gating as before.
- Auth: register, login, email OTP verification, forgot/reset password -
  all session-based now (cookies), not the old JWT+refresh-token pair.
- Reader actions: like, bookmark, reading-progress tracking (Alpine posts
  scroll position in the background as you read).
- Story purchases: redirects to the gateway's own hosted checkout page
  (Paystack/Flutterwave/Stripe) - no client-side payment SDK needed. Webhook
  handler ported as-is.
- Profile: update name/currency, change password, request author access
  (reader -> pending -> admin grants, same flow as before).
- **Author studio** (`/studio`, `role` author or admin): dashboard with own
  stats (platform-wide stats too, for admins), pen name management, story
  CRUD (categories/genres/prices/publish/unpublish/delete), episode CRUD
  (create/edit/publish/delete) under each story, an image-upload endpoint
  for covers/avatars. Everyone scoped to their own pen names; admins see and
  can manage everyone's.
- **Admin users page** (`/admin/users`, `role` admin only): filter by
  request status/role, search, grant author access (with or without a prior
  request), decline a pending request, revoke an author back to reader
  (optionally unpublishing all their stories in the same action) - this is
  the same flow built for the old backend, ported to Blade.
- Telescope, simplified: real gate-based admin auth now (visit `/telescope`
  signed in as an admin), no more access-key-in-URL workaround - that only
  existed because the old API had no session login to gate on.

## What's intentionally left out (for now)

Dropped as complexity that doesn't pull its weight yet, not lost - the
underlying DB tables/models still exist if you want any of these back later:

- **AI features** - AI-powered search (`laravel/ai`) and AI episode-text
  styling. Both needed an external API key, a scheduled job, and an extra
  package; episodes just render their existing `content` field as-is.
- **Push notifications / in-app notification bell** - per your note to stop
  instant notifications for now. Account-related emails (OTP codes, welcome)
  still send normally; nothing else does.
- **Google sign-in** - the email/password + OTP flow covers auth; Google's
  JS SDK is a separate small add-on if you want it back.
- **Referral attribution & signup-attempt analytics** - acquisition-funnel
  tooling, not core to reading/publishing. `ReferralService` and the
  `signup_attempts` table are already ported, just not wired into the
  register/login forms yet.
- **Admin panel & author studio** - core CRUD is now built (see above).
  Still missing: platform-wide analytics beyond the basic monetization
  status, email blacklist management, payouts, and story-import tooling
  (all existed in the old `/admin` API but have no Blade screen yet).
- **Badges, tips, subscriptions** - models and a couple of services exist
  (`TipService`, `BonusAccessService`) but nothing calls them yet.
- **CraftProfessor export** - the one genuinely external JSON contract
  (`GET /api/stories/{slug}/json`) is ported as-is, since it's a fixed API
  another system depends on, not part of this app's own UI.

No voice-reading feature existed anywhere in the old codebase, so there was
nothing to remove there.

## Why these particular choices

- **MySQL only, file cache/sessions, sync queue** - matches the cPanel
  target exactly (see `DEPLOYMENT.md` in the repo root for why): no redis,
  no background worker process, nothing shared hosting can't run.
- **No `routes/api.php` at all** - every route, including the payment
  webhooks, lives in `routes/web.php`. The one JSON response
  (CraftProfessor's export) is a single explicit route, not a reason to
  stand up a separate API surface for an app that otherwise has none.
- **Tailwind + Alpine via Vite, npm build only in CI** - the cPanel server
  itself only ever runs PHP; `public/build/*` ships as already-compiled
  static assets, same as the old frontend's `npm run build` step.

## One-time server setup (cPanel)

Same shape as the old backend's setup (see repo root `DEPLOYMENT.md`), minus
the separate frontend document root:

1. Create/reuse the MySQL database - same one `backend/` used works
   unchanged, since the schema is identical.
2. Point `storyverse.amowogbaje.com`'s document root at
   `<this-app>/public`.
3. Create `.env` directly on the server from `.env.cpanel.example` in this
   folder - fill in real DB credentials, `APP_KEY`, mail, and payment keys.
   Never committed to git.
4. First deploy only:
   ```bash
   cd /home/youruser/storyverse-fullstack
   php artisan key:generate
   php artisan migrate --force
   php artisan storage:link
   ```
5. Cron, same as before - every minute:
   ```
   * * * * * php /home/youruser/storyverse-fullstack/artisan schedule:run >> /dev/null 2>&1
   ```

## GitHub repo secrets for the new workflow

`.github/workflows/deploy-fullstack.yml` needs:

| Secret | Example |
|---|---|
| `CPANEL_HOST` | `storyverse.amowogbaje.com` or the server IP |
| `CPANEL_USER` | your cPanel SSH username |
| `CPANEL_SSH_KEY` | private key, full contents |
| `CPANEL_SSH_PORT` | usually `22` |
| `CPANEL_FULLSTACK_PATH` | `/home/youruser/storyverse-fullstack` |

Every push to `main` that touches `fullstack/**` builds it and rsyncs it
straight to that path with `--delete` - so anything not in this folder gets
removed from the server, which is the "erase and replace" behavior you
asked for. `.env`, `storage/`, and `public/storage` are explicitly excluded
from that sync, so what you set up manually on cPanel is never touched by a
deploy.

## About the old `ci.yml`

**Heads up:** the zip you gave me doesn't actually contain
`.github/workflows/ci.yml` - it wasn't in the export, so I can't edit it
directly. In your real repo, open it and do one of:

- Simplest: delete or comment out the whole `deploy-cpanel` job (the one
  `DEPLOYMENT.md` describes - builds `frontend/`, uploads both folders over
  SSH).
- Or narrower: add `if: false` as the first line under that job, which
  disables just that job while leaving any test/lint jobs running normally
  against `backend/`/`frontend/` on every push.

Either way, once that job is disabled, `deploy-fullstack.yml` here is the
only thing that ships anything to the live site.

## Monitoring

Telescope is on in production, gated to `role === 'admin'` (see
`app/Providers/TelescopeServiceProvider.php`) - sign in as an admin, visit
`/telescope`. No separate access key, no unlock step; it uses the same
session login as everything else, which is what makes this simpler than
the old backend's setup (that app had no session auth for Telescope to gate
on at all).

`storage/logs/performance.log` still gets slow-request/slow-query entries
independent of Telescope (see `LogSlowRequests` middleware and
`AppServiceProvider::logSlowQueries()`) - useful even with Telescope's UI
closed, e.g. via SSH: `tail -f storage/logs/performance.log`.

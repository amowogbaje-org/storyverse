# Deploying Storyverse to cPanel

This is the primary deploy target right now: no Docker, no Redis, no Meilisearch,
no standing queue worker or scheduler process on the server. Just PHP + MySQL,
uploaded over SSH by `.github/workflows/ci.yml` (the `deploy-cpanel` job).

## One-time server setup

1. **Create a MySQL database.** cPanel → MySQL Databases → create a database and a
   user, add the user to the database with all privileges. Note the full names
   (cPanel prefixes both with your account name, e.g. `myuser_storyverse`).
2. **Pick where the backend lives.** It should be *outside* your public web root -
   e.g. `/home/myuser/storyverse-backend` - since Laravel's `public/` folder is the
   only part that should ever be web-accessible, and everything else (`.env`,
   `app/`, migrations) must not be.
3. **Point a domain/subdomain's document root at `storyverse-backend/public`.**
   cPanel → Domains (or Subdomains) → set the document root to
   `storyverse-backend/public`. This becomes your API URL, e.g. `api.yourdomain.com`.
4. **Point your main domain's document root at the frontend build.** e.g.
   `/home/myuser/public_html` (cPanel's default) for `yourdomain.com`, which is
   where `frontend/dist/*` gets uploaded to.
5. **Copy `.env.cpanel.example` to `.env`** in the backend directory, and fill in
   the real DB credentials, `APP_URL` (your API subdomain), `FRONTEND_URL` (your
   main domain), and mail/payment/OpenAI keys as you get them. This file is never
   committed to git - create it directly on the server, once.
6. **Generate an app key and run migrations** (first deploy only, or after
   `.env` changes):
   ```bash
   cd /home/myuser/storyverse-backend
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --force   # optional - seeds categories, badges, an admin account
   php artisan storage:link
   ```
7. **Set up cron**, replacing the standing `scheduler` container from local dev.
   cPanel → Cron Jobs → add, every minute:
   ```
   * * * * * php /home/myuser/storyverse-backend/artisan schedule:run >> /dev/null 2>&1
   ```
8. **SSH keys for CI.** Generate a deploy key pair, add the public key to
   cPanel → SSH Access → Manage SSH Keys → Import, then authorize it. Add the
   private key as the `CPANEL_SSH_KEY` GitHub secret (see below).

## GitHub repo secrets (Settings → Secrets and variables → Actions)

| Secret | Example |
|---|---|
| `CPANEL_HOST` | `yourdomain.com` or the server's IP |
| `CPANEL_USER` | your cPanel SSH username |
| `CPANEL_SSH_KEY` | the private key from step 8, full contents |
| `CPANEL_SSH_PORT` | usually `22`, sometimes cPanel uses something else - check cPanel → SSH Access |
| `CPANEL_BACKEND_PATH` | `/home/myuser/storyverse-backend` |
| `CPANEL_FRONTEND_PATH` | `/home/myuser/public_html` |

And one repo **variable** (not secret, it's not sensitive): `VITE_API_URL` set to
your API subdomain, e.g. `https://api.yourdomain.com/api/v1`.

Also add `VAPID_PUBLIC_KEY` as a repo **variable** (again, not secret - it's the
public half of the push-notification key pair, meant to ship to the browser) once
you've generated it - see "Push notifications" below.

## Every push to `main` after that

`deploy-cpanel` in `ci.yml` runs automatically: tests pass → frontend builds with
`npm run build` → both are uploaded over SSH → `composer install --no-dev`,
`migrate --force`, `config:cache`, `route:cache` run on the server. No manual steps.

## Why no queue worker or scheduler daemon

Shared cPanel hosting generally doesn't let you run a long-lived background
process (`php artisan queue:work`) the way a VPS or `compose.yaml`'s `queue`
container does. `QUEUE_CONNECTION=sync` sidesteps this entirely: queued work
(right now, just the badge-award listener) runs inline, synchronously, during
the request that triggered it. That's a real tradeoff - a request that awards a
badge is very slightly slower - but it's the right one at this scale, and it
means zero background processes to babysit. If you outgrow this, cPanel's cron
can also run `php artisan queue:work --stop-when-empty` every minute as a
poor-man's worker, without needing shell/systemd access.

## Caching (homepage lists + episode previews)

Two things are cached to keep the highest-traffic pages fast:

- **Homepage lists** (`/stories/new-releases`, `/stories/popular`) - the shared
  story data (titles, covers, counts) for 5 minutes. See `App\Support\HomeCache`.
- **Episodes 1-2 of every story** - the platform-wide free preview every reader
  can always open, so the same content goes out to everyone. Cached for 30
  minutes. See `EpisodeController::loadEpisodePayload()`/`forgetPreviewCache()`.

Both are busted immediately on the actions that matter (publish/unpublish/edit
a story, edit/publish/delete an episode) rather than waiting out the TTL - see
the `HomeCache::forgetHomepage()` / `EpisodeController::forgetPreviewCache()`
calls in the admin controllers.

Per-user data (whether *you* liked/bookmarked a story, your reading progress)
is never part of the cached payload - it's looked up fresh on every request and
merged in afterward, so cached results can't leak one reader's state to another.

This all goes through Laravel's plain `Cache` facade - no redis-only features
like tags - so it works unchanged on both targets:

- **Docker/cloud** (`CACHE_STORE=redis`): shared across all backend/queue/
  scheduler containers, survives container restarts.
- **cPanel** (`CACHE_STORE=file`): per-server filesystem cache under
  `storage/framework/cache/data`. Works fine for a single-server deploy; if you
  ever scale to multiple app servers on the same account without redis, switch
  to `CACHE_STORE=database` (add a `cache` table via `php artisan cache:table`)
  so all servers share one cache instead of each having its own.

## Push notifications

One-time setup, both environments:

1. Generate a VAPID key pair:
   `php artisan tinker --execute="print_r(Minishlink\WebPush\VAPID::createVapidKeys());"`
2. Backend `.env`: `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` (a
   `mailto:` address push services can contact if this server misbehaves).
3. Frontend needs the **public** key only, as `VITE_VAPID_PUBLIC_KEY` - it's baked
   in at build time (Vite), not read at runtime, so:
   - **Docker**: already wired in `compose.yaml` from `VAPID_PUBLIC_KEY` in your
     shell env - `export $(grep VAPID_PUBLIC_KEY backend/.env)` before `docker
     compose up`, same as the existing `GOOGLE_CLIENT_ID` pattern.
   - **cPanel/CI**: add `VAPID_PUBLIC_KEY` as a GitHub repo **variable** (see
     above) - `ci.yml`'s frontend build step already passes it through.

Leave any of this unset and push notifications just quietly don't offer
themselves (`isPushSupported()` on the frontend checks for the key; the backend's
`WebPushChannel` no-ops without one) - nothing else breaks either way.

The three re-engagement jobs (new-story recommendations, continue-reading
reminders, "we missed you") are plain scheduled commands - see "Why no queue
worker or scheduler daemon" below for how they run on cPanel.

## CraftProfessor series export

`GET /api/stories/{slug}/json` implements the contract in
`storyverse-api-docs.md` (supplied by CraftProfessor). Two things worth
knowing:

- It's registered **outside** the `/v1` prefix everything else in this app
  uses, since CraftProfessor's contract is a fixed path with no version
  segment. If your production frontend and backend share one domain (rather
  than a separate API subdomain), double-check that `/api/stories/*` on that
  domain actually reaches the Laravel backend and isn't swallowed by the
  frontend's static-file serving/.htaccess rewrite - a proxy rule may be
  needed depending on how you've split the two.
- Full episode `content` is capped at `CRAFTPROFESSOR_CONTENT_BATCH_SIZE`
  (default 40) per call, not the whole series every time - see
  `CraftProfessorExportController`'s docblock for why, and why that's safe per
  the spec's own re-import behavior.

## AI search (Laravel AI SDK)

Native search never depends on this - see `AiSearchService`'s docblock. Setup:

1. `composer require laravel/ai` (if the version in `composer.json` doesn't
   resolve, just re-run this to let Composer pick the current version)
2. `php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"`
3. `php artisan migrate` (creates the SDK's own conversation-storage tables -
   unused by AiSearchService today, but needed for the package to work at all)
4. Set `GEMINI_API_KEY` in `.env`. `AI_SEARCH_PROVIDER` (already defaults to
   `gemini`) controls which provider `App\Ai\Agents\StorySearchAgent` uses -
   switching to OpenAI later, once you have a key for it, is just changing
   that value and setting `OPENAI_API_KEY` - no code change.

## If something doesn't boot

- **500 error, blank page**: check `storage/logs/laravel.log` first. Almost
  always either `APP_KEY` not set (`php artisan key:generate`) or `storage/`
  not writable (`chmod -R 775 storage bootstrap/cache`).
- **"could not find driver" on DB connection**: your cPanel PHP version's
  `pdo_mysql` extension isn't enabled - cPanel → MultiPHP INI Editor → enable it,
  or ask your host.
- **Cover image upload fails on a file under 8MB**: shared hosting's default
  `upload_max_filesize`/`post_max_size` (often 2M/8M) can reject it before
  Laravel ever sees it. cPanel → MultiPHP INI Editor → raise both to at least
  `10M`/`12M`. Also confirm the `gd` PHP extension is enabled there - it's what
  `ImageOptimizerService` resizes/compresses the upload with, and it's on by
  default on almost every host, but worth checking if uploads 500.
- **Frontend loads but every route except `/` 404s**: shouldn't happen -
  `frontend/public/.htaccess` (auto-copied into every `npm run build` output by
  Vite) already handles the client-side-routing rewrite. If it's still
  happening, check that Apache's `mod_rewrite` is enabled for your domain
  (cPanel → it's on by default, but ask your host if unsure) and that the
  `.htaccess` file actually made it into your document root after deploy.

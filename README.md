# Storyverse

Serialized-fiction reading platform: React/Vite reader site + author/admin studio,
Laravel 13 API. This README is the map — what's here, how to run it, and what's
genuinely still missing.

## Quick start (local Docker dev)

```bash
cp backend/.env.example backend/.env

docker compose build          # compose.yaml is picked up automatically
docker compose up -d postgres redis mailpit meilisearch

docker compose run --rm backend php artisan key:generate
docker compose run --rm backend php artisan migrate --seed

docker compose up -d
```

- Frontend: http://localhost:5173
- API: http://localhost:8000/api/v1 (health check: http://localhost:8000/up)
- Mailpit UI: http://localhost:8025 · Adminer: http://localhost:8081 · Meilisearch: http://localhost:7700

**Everything in `compose.yaml` (Redis, Meilisearch, Selenium, standing queue/scheduler
containers) is optional local-dev convenience, not a requirement.** The app runs
without any of it — see "Deployment" below.

## Where's the admin panel?

It's there, at **`/admin`** in the frontend — role-gated, not a separate app.

- **Platform admin** (sees everything, all analytics): log in as the seeded account,
  `admin@storyverse.local` / `password` (from `AdminUserSeeder`, runs as part of
  `migrate --seed`). Change or remove this before deploying anywhere real.
- **Author** (manages only their own stories): register a normal reader account, then
  go to **Settings → "Become an author"**. That flips your role and drops you into
  the same `/admin` UI, scoped to your own content.

From there: **Dashboard** (your stats, or platform-wide if admin) → **Stories** (create,
edit, publish) → click into a story to add/publish **episodes** → **Pen names** →
**Earnings** → **Platform analytics** (admin only, charts).

## What's built

- **Reader site**: home, browse (grid/list, filters), story detail, episode reader with
  progress tracking, native + AI search, auth, library, badges, subscription checkout
- **Author/admin studio**: pen names, story + episode CRUD with draft/publish states,
  per-author stats, revenue-share earnings estimate, platform-wide analytics with charts
- **Access control**: 2 free episodes for guests / 5 for registered readers / unlimited
  for premium subscribers or on free stories, a bootstrap phase where everything is
  free until the platform hits 10k reads + 8k completions, and grandfathering so a
  subscription requirement never locks someone out of a book they already started
- **Payments**: real Stripe/Paystack/Flutterwave checkout + webhook verification,
  structured around a `PaymentGateway` interface (SOLID — see below) so adding a new
  provider doesn't touch existing code
- **Badges**: 39 of 40 criteria types have working resolvers (one needs infrastructure
  that doesn't exist yet — see `BadgeMetricResolver`)
- **Analytics**: visits, reads, engagement, funnel, top stories — live queries, not a
  nightly rollup, since that's simpler at this scale
- **Tests**: 9 feature files + 1 unit file, ~65 cases, covering access rules, payment
  webhooks (incl. replay-safety), badge resolvers, admin ownership scoping, AI search
  fallback behavior
- **CI**: `.github/workflows/ci.yml` runs the full suite against **both Postgres and
  MySQL** (a real matrix, not just an assertion) on every push, builds the frontend,
  and deploys to cPanel on `main`

## What's genuinely still missing

1. **Nothing here has actually been executed.** My sandbox can't reach packagist.org,
   so `composer install`, booting Laravel, and running the test suite have only ever
   been simulated via `php -l` syntax checks — never actually run. First thing to do:
   `docker compose run --rm backend php artisan test` (or let CI do it).
2. `bookmarked_before_trending` badge criteria has no resolver (needs a view-count
   snapshot at bookmark time, which isn't tracked — see the comment in
   `BadgeMetricResolver`).
3. Payment gateways make real API calls but haven't touched an actual Stripe/Paystack/
   Flutterwave test account yet, only `Http::fake()` in tests.
4. No self-serve "become an author" review step — it's instant, deliberately simple.

## Deployment

Two paths, pick one:

- **cPanel / bare-metal** (the current primary target) — plain PHP + MySQL, no Docker,
  no Redis, no queue worker daemon, no scheduler daemon. `QUEUE_CONNECTION=sync` and a
  cron entry replace what `compose.yaml`'s `queue`/`scheduler` containers do locally.
  Full walkthrough: **[DEPLOYMENT.md](./DEPLOYMENT.md)**. Copy
  `backend/.env.cpanel.example`, not `.env.example`, on the server.
- **Docker on a cloud VPS** — `compose.prod.yaml`: three services (backend, frontend,
  one database), no Redis/Meilisearch/Selenium, images built and run as-is (no source
  bind-mounts). Deploy via the manually-triggered
  `.github/workflows/deploy-docker-cloud.yml`.

Both deploy jobs upload files over SSH (`appleboy/scp-action` + `appleboy/ssh-action`);
neither requires a Docker registry.

### About that OCI runtime error

```
failed to create shim task: ... exec: "./docker-entrypoint.sh": permission denied
```

This happens when a container's `ENTRYPOINT`/`CMD` points at a shell script that
either (a) wasn't `chmod +x`'d when the image was built, or — more commonly — (b) *was*
made executable in the image, but a `volumes:` bind-mount at runtime (like
`./backend:/var/www/html` in `compose.yaml`, used for dev hot-reload) overlays it with
a host copy that lost its executable bit. Very common on Windows/Docker Desktop and in
some CI checkouts.

**Neither `backend/Dockerfile` nor `frontend/Dockerfile`/`Dockerfile.dev` in this repo
use a shell entrypoint script at all** — `CMD` calls `php`/`npm` directly, in exec
form, specifically to avoid this entire class of bug. Also added `.gitattributes`
(`* text=auto eol=lf`) repo-wide, since CRLF line endings on a script can produce a
related "bad interpreter" error on Linux — belt and suspenders.

### A real bug this surfaced: `backend/Dockerfile`'s composer install was silently failing

Building this for real (first time it's actually been built, not just `php -l`'d) hit
a genuine bug: the Dockerfile split `composer install` into a cached "install deps"
layer (using `--no-scripts --no-autoloader`) followed by a separate `composer
dump-autoload` layer after copying the app code. That second step triggers `php
artisan package:discover`, which needs a fully-installed `vendor/` — and the first
step's command ended in `|| true`, which silently swallowed any failure there. If that
install failed for any reason, the build kept going with a broken `vendor/` and only
surfaced a confusing, unrelated-looking error (`Class Illuminate\Foundation\Application
not found`) two layers later.

Fixed: the Dockerfile now runs one plain `composer install` after the full app is
already copied in — slower to rebuild on every code change (no separate cached deps
layer), but a single step that either fully succeeds or fails loudly, with no silent
partial state possible. Also added `backend/.dockerignore` and `frontend/.dockerignore`
(neither existed before), so a stale local `vendor/`/`node_modules/`/`.git` on your
host machine can never get copied into the build context in the first place. The same
Dockerfile now takes a `COMPOSER_ARGS` build arg so `compose.prod.yaml` can pass
`--no-dev` for production while `compose.yaml` keeps dev dependencies (phpunit, etc.)
by default.

## Laravel version

Upgraded from Laravel 11 to **Laravel 13.8** (current stable as of this update),
matching the real `laravel/laravel` 13.x skeleton's `composer.json` — PHP `^8.3`
(already what `backend/Dockerfile` used), `laravel/tinker` `^3.0`,
`nunomaduro/collision` `^8.6`, `phpunit/phpunit` `^12.5`. Laravel 13 is explicitly a
"zero breaking changes" release from a property-based-config app's point of view (its
headline feature, PHP attributes for model config, is opt-in), so nothing in `app/`
needed to change for the version bump itself.

**No `composer.lock` is committed.** I can't generate a real one — my sandbox has no
packagist.org access, and a hand-written lock file's package hashes wouldn't validate
against Composer's integrity checks, which would break `composer install` worse than
not having one at all. `composer install` will resolve and write a fresh, correct lock
file the first time you run it (in Docker, in CI, or on the cPanel server) — that's
normal for any Laravel project on a fresh clone.

**Removed from `composer.json`**: `laravel/sail` (not used — this repo has its own
Dockerfiles, and Laravel 13's own skeleton dropped Sail from `require-dev` too),
`laravel/horizon` and `laravel/scout` + `meilisearch-php` (not wired into any code path
yet — native search works standalone via plain SQL `ILIKE`/`LIKE`; re-add these if you
actually implement Meilisearch-backed search later, the config file for it is still
there, just inert).

## Database portability (Postgres + MySQL)

cPanel hosting is MySQL-only almost universally; `compose.yaml`/`compose.prod.yaml`
default to Postgres. `config/database.php` now defines both connections (plus `sqlite`
for fast local testing) — set `DB_CONNECTION` in `.env` to whichever you're using.

Nearly all of the app is plain Eloquent/Schema Builder, which is already portable. Four
raw-SQL fragments in `BadgeMetricResolver` (`to_char`/`date_format`,
`extract(hour...)`/`hour()`, and two `interval` literals) were Postgres-only and would
have thrown SQL errors on MySQL — now branch on `DB::connection()->getDriverName()`.
CI's matrix (`db: [pgsql, mysql]`, above) runs the full suite against both on every
push specifically to keep this honest going forward, not just at the moment of the fix.

## `.env.example` cleanup

The file had accumulated real duplicate keys (`STRIPE_SECRET`, `MAIL_*`,
`FLUTTERWAVE_*`/`PAYSTACK_*` each listed twice) from edits across several rounds of
changes — rewritten clean. There are now two example files:
`backend/.env.example` (Docker dev — Redis/Meilisearch on) and
`backend/.env.cpanel.example` (bare-metal — file cache, sync queue, MySQL, no Redis).
Also changed the *defaults* in `config/cache.php`/`session.php`/`queue.php` themselves
from `redis` to `file`/`file`/`sync`, so a fresh clone with zero `.env` overrides just
works without Redis being reachable at all — Redis is now opt-in, not assumed.

## Everything else (payments, badges, AI search, SOLID refactor, analytics, tests)

Covered in detail in git history / prior conversation — this README stays focused on
current state and how to run things. The short version: payment gateways are behind a
`PaymentGateway` interface with one implementation class per provider (Stripe/Paystack/
Flutterwave), a registry, and a service provider — adding a 4th provider means one new
class, zero changes to existing controllers (Open/Closed). See
`app/Contracts/PaymentGateway.php` and `app/Services/PaymentGateways/*` if you're
looking for that code.

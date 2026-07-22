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

## If something doesn't boot

- **500 error, blank page**: check `storage/logs/laravel.log` first. Almost
  always either `APP_KEY` not set (`php artisan key:generate`) or `storage/`
  not writable (`chmod -R 775 storage bootstrap/cache`).
- **"could not find driver" on DB connection**: your cPanel PHP version's
  `pdo_mysql` extension isn't enabled - cPanel → MultiPHP INI Editor → enable it,
  or ask your host.
- **Frontend loads but every route except `/` 404s**: shouldn't happen -
  `frontend/public/.htaccess` (auto-copied into every `npm run build` output by
  Vite) already handles the client-side-routing rewrite. If it's still
  happening, check that Apache's `mod_rewrite` is enabled for your domain
  (cPanel → it's on by default, but ask your host if unsure) and that the
  `.htaccess` file actually made it into your document root after deploy.

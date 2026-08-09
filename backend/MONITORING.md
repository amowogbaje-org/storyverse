# Monitoring & performance

Two complementary tools, for two different situations.

## Already active — slow-request / slow-query logging

No install step, works right now. Logs to `storage/logs/performance-*.log`
(separate from the main app log on purpose, so it's easy to `tail -f` on its
own).

- **Slow requests** (`App\Http\Middleware\LogSlowRequests`, registered
  globally in `bootstrap/app.php`): any request over
  `SLOW_REQUEST_THRESHOLD_MS` (default 1000ms) logs the route, duration,
  query count, response status, and the signed-in user's ID.
- **Slow queries** (`AppServiceProvider::logSlowQueries`): any single query
  over `SLOW_QUERY_THRESHOLD_MS` (default 200ms) logs the SQL, bindings, and
  duration. Runs for console commands and queue jobs too, not just HTTP
  requests.

Tune both via `.env` (`SLOW_REQUEST_THRESHOLD_MS`, `SLOW_QUERY_THRESHOLD_MS`).
Start wide, watch `storage/logs/performance-*.log` for a few days under real
traffic, then tighten once you know your actual baseline — a threshold set
too low just produces noise.

**This is the first place to look if the site feels slow.** Grep the file
for `"slow query"` — a query duration that jumps out immediately tells you
whether the problem is one bad query (missing index, N+1) versus a page that
legitimately does a lot of work.

## Not yet installed — Laravel Telescope

A full request/query/exception/job inspector with a UI at `/telescope`.
Heavier than the logging above (records much more per request), so it's
better suited to actively investigating something than running unattended in
production forever.

Already prepared, not yet wired up:
- `laravel/telescope` is in `composer.json` (`require-dev`)
- `app/Providers/TelescopeServiceProvider.php` exists, customized to gate
  dashboard access on `role === 'admin'` (this app's actual permission
  model) instead of Telescope's default hardcoded email list
- A pruning schedule (`telescope:prune --hours=48`, daily) is already
  registered in `routes/console.php`, guarded so it's a no-op until Telescope
  is actually installed

**To finish setting it up:**

```bash
composer install
php artisan telescope:install
php artisan migrate
```

Then open `bootstrap/providers.php` and confirm
`App\Providers\TelescopeServiceProvider::class` is listed — `telescope:install`
usually adds this automatically, but it's worth checking since our
customized provider file already existed before you ran the installer, so
it won't have been touched by that step (which only ever avoids overwriting
files, not registering them).

Set `TELESCOPE_ENABLED=false` in production `.env` unless you're actively
debugging something. This env var is already in `.env.example`; it'll take
effect automatically because Telescope's own published `config/telescope.php`
reads `'enabled' => env('TELESCOPE_ENABLED', true)` by default - nothing
further to wire up on our side, just confirm that line is still there after
`telescope:install` generates the file.

### Accessing the dashboard (JWT app, no session login)

This app authenticates via a JWT bearer token on every API request - there's
no Laravel session/web-guard login anywhere, which is what Telescope's
default `Authorize` middleware expects to check against
(`Auth::user()` + the `viewTelescope` gate in `TelescopeServiceProvider`).
Visiting `/telescope` in a plain browser tab has no bearer token attached at
all, so that gate can never pass - you'd be locked out regardless of role.

`App\Http\Middleware\TelescopeAccessKey` replaces that with a shared-secret
passkey instead. To wire it up, after running `telescope:install`, open your
generated `config/telescope.php` and find the `middleware` array - swap out
`Laravel\Telescope\Http\Middleware\Authorize::class` for
`App\Http\Middleware\TelescopeAccessKey::class`.

Then:

1. Generate a real secret and set it in `.env`: `TELESCOPE_ACCESS_KEY=$(openssl rand -hex 32)`
2. Visit `https://yourapp.test/telescope?key=<that secret>` once
3. It unlocks Telescope for your current browser session (not a persistent
   cookie with the key in it - just a session flag) and immediately
   redirects to strip `?key=...` back out of the address bar
4. You'll need to repeat step 2 whenever the session ends (browser closed,
   session lifetime expires)

Two things worth being deliberate about:
- The key itself does still pass through the URL once per unlock, which
  means it can land in server access logs even though it's stripped from
  the browser's address bar immediately after - treat it like any other
  credential (rotate it if you think it leaked, don't paste URLs containing
  it into chat/screenshots).
- This is a shared secret, not per-person access - anyone with the key can
  see everything Telescope records (request bodies, query bindings, etc,
  minus what's redacted - see `hideRequestParameters`/`hideRequestHeaders` in
  `TelescopeServiceProvider`). Fine for a small team; if that stops being
  true, this is the first thing to revisit.

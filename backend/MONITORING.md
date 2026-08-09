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

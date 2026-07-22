#!/usr/bin/env bash
set -e

cd /var/www/html

if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
  cp .env.example .env
fi

# Wait for Postgres to accept connections before touching migrations.
until php -r "new PDO('pgsql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
  echo "Waiting for Postgres..."
  sleep 2
done

if [ "$RUN_MIGRATIONS" = "true" ]; then
  if ! grep -q "^APP_KEY=base64" .env; then
    php artisan key:generate --force
  fi

  if grep -q "^JWT_SECRET=$" .env; then
    sed -i "s/^JWT_SECRET=$/JWT_SECRET=$(openssl rand -hex 32)/" .env
  fi

  php artisan migrate --force

  # Only seed once: touch a marker file so `docker compose up` on subsequent
  # runs doesn't re-run updateOrCreate seeders against an already-seeded DB.
  if [ ! -f storage/.seeded ]; then
    php artisan db:seed --force
    touch storage/.seeded
  fi

  php artisan storage:link || true
fi

exec "$@"

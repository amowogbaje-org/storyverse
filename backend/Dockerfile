# Deliberately no docker-entrypoint.sh / shell entrypoint anywhere in this image.
# CMD below calls the php binary directly, in exec form.

FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl libpng-dev libonig-dev libxml2-dev libzip-dev libpq-dev \
        default-mysql-client zip unzip \
    && docker-php-ext-install pdo_pgsql pgsql pdo_mysql mysqli mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Everything copied in before composer install runs - deliberately not split
# into a "deps first, then code" caching trick. That split needs php artisan
# package:discover (composer's post-autoload-dump script) to run against a
# fully-installed vendor/ *and* the full app source at the same time, which is
# fragile to get right and was exactly what broke here previously. This is
# slower to rebuild on every code change, but it's a single, honest step that
# either fully succeeds or loudly fails - no silent partial state possible.
COPY . .

# Build arg so the same Dockerfile serves both compose.yaml (dev, wants
# phpunit/mockery/faker/pint) and compose.prod.yaml (production, --no-dev).
ARG COMPOSER_ARGS="--no-interaction --no-progress --optimize-autoloader"
RUN composer install ${COMPOSER_ARGS} \
    # storage/ and bootstrap/cache/ must be writable by whatever user php runs
    # as - baked into the image so it's correct regardless of host OS/UID.
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s \
    CMD curl -f http://localhost:8000/up || exit 1

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

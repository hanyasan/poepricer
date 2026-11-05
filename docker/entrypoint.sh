#!/bin/sh
set -e

cd /var/www/html

# With APP_KEY supplied through the environment (production) no .env is needed.
# The production image ships without .env.example on purpose, so a missing key
# is a configuration error rather than something to paper over at boot.
if [ ! -f .env ] && [ -z "${APP_KEY:-}" ]; then
    if [ ! -f .env.example ]; then
        echo "APP_KEY is not set and .env.example is not present in the image." >&2
        echo "Set APP_KEY in the environment file used by this container." >&2
        exit 1
    fi

    cp .env.example .env
    php artisan key:generate --force
fi

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

echo "Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT}..."

until php -r '
    $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE"));

    try {
        new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
    } catch (Throwable $exception) {
        exit(1);
    }
'; do
    sleep 2
done

# Only one container should migrate; queue workers and the scheduler set RUN_MIGRATIONS=false.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

# Cache config, events and routes in production. This has to happen here and not
# at image build time: config:cache freezes env() values, and the secrets only
# exist once the container starts. Every container (php-fpm, Horizon, scheduler)
# has its own filesystem, so each one caches against its own environment and a
# restart always refreshes the cache. Set RUN_OPTIMIZE=false to skip it.
# Views are skipped while the app has no resources/views, otherwise view:cache fails.
if [ "${APP_ENV:-}" = "production" ] && [ "${RUN_OPTIMIZE:-true}" = "true" ]; then
    if [ -d resources/views ]; then
        php artisan optimize
    else
        php artisan optimize --except=views
    fi
fi

# A command passed to the container (queue:work, schedule:work, ...) replaces the web server.
if [ "$#" -gt 0 ]; then
    exec "$@"
fi

# Run PHP's built-in server directly instead of `artisan serve`, which strips
# the DB_* and REDIS_* environment variables from the server process.
exec php -S 0.0.0.0:8000 -t public docker/router.php

#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
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

php artisan migrate --force

# Run PHP's built-in server directly instead of `artisan serve`, which strips
# the DB_* and REDIS_* environment variables from the server process.
exec php -S 0.0.0.0:8000 -t public docker/router.php

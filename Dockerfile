# cli for development (php -S); fpm for production. The fpm image also ships
# the php CLI, so queue workers and the scheduler run from the same image.
ARG PHP_VARIANT=cli

FROM php:8.4-${PHP_VARIANT} AS base

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libfcgi-bin \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install pdo_pgsql pgsql pcntl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN groupadd --gid "${GID}" app \
    && useradd --uid "${UID}" --gid "${GID}" --create-home --shell /bin/bash app

WORKDIR /var/www/html

RUN chown app:app /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint"]

# Production: the code is baked into the image, dev dependencies are skipped.
FROM base AS production

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-prod.conf

USER app

COPY --chown=app:app . .

# Stale caches from the host would be baked in and reference dev-only providers.
RUN rm -f bootstrap/cache/*.php \
    && mkdir -p \
        bootstrap/cache \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
    && php artisan package:discover --ansi

# Production web server: nginx serves static files from public/ and hands
# index.php to php-fpm. It carries its own copy of public/, so no volume is
# shared with the php-fpm container.
FROM nginx:stable-alpine AS nginx

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=production /var/www/html/public /var/www/html/public

# Development (default target): the code is mounted from the host.
FROM base AS development

USER app

FROM php:8.4-cli

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
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

COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint

USER app

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint"]

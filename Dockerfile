FROM php:8.4-cli AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev \
    && docker-php-ext-install intl zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

FROM base AS app

COPY . .

RUN composer install --no-interaction --prefer-dist --no-progress

EXPOSE 8000

ENTRYPOINT ["sh", "docker/entrypoint.sh"]

# syntax=docker/dockerfile:1

# ---- PHP dependencies (no dev packages) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts

# ---- Front-end assets ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
RUN npm run build

# ---- Runtime: one image, four roles chosen by CONTAINER_ROLE ----
FROM dunglas/frankenphp:1-php8.3 AS runtime

RUN install-php-extensions pdo_mysql intl gd zip opcache redis pcntl bcmath exif

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=":80" \
    CONTAINER_ROLE=web \
    LARABB_ENV_FILE=/app/storage/app/.env

WORKDIR /app
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/larabb-entrypoint
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-larabb.ini

RUN chmod +x /usr/local/bin/larabb-entrypoint \
    && mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

# Non-root. Port 80 needs a capability because FrankenPHP binds it directly.
RUN setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && chown -R www-data:www-data /data/caddy /config/caddy
USER www-data

EXPOSE 80 443
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r "exit(@file_get_contents('http://127.0.0.1/healthz') === false ? 1 : 0);" || exit 1

ENTRYPOINT ["larabb-entrypoint"]

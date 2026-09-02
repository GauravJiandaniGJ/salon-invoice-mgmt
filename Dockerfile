# syntax=docker/dockerfile:1
# ---------- 1. PHP dependencies ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader --ignore-platform-reqs

# ---------- 2. Frontend assets (needs vendor/tightenco/ziggy for the Ziggy import) ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
COPY --from=vendor /app/vendor/tightenco ./vendor/tightenco
RUN npm run build

# ---------- 3. Runtime: Nginx + PHP-FPM in one container ----------
FROM serversideup/php:8.3-fpm-nginx AS app
ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=false \
    SSL_MODE=off

USER root
WORKDIR /var/www/html
RUN install-php-extensions pdo_sqlite gd intl >/dev/null

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
COPY docker/entrypoint.d/ /etc/entrypoint.d/

RUN chmod +x /etc/entrypoint.d/*.sh \
    && rm -rf node_modules tests .github docker \
    && mkdir -p database storage/app/private/invoices storage/app/public storage/backups storage/logs \
    && chown -R www-data:www-data database storage bootstrap/cache

USER www-data
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s CMD curl -fsS http://localhost:8080/up || exit 1

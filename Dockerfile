# syntax=docker/dockerfile:1
# ─────────────────────────────────────────────────────────────
# An-Nur Smart System — image produksi (multi-stage)
# Stage 1: build asset Vite (Vue)  →  Stage 2: composer deps  →  Stage 3: runtime
# Runtime = php-fpm + nginx + supervisor (php-fpm, nginx, queue:work, scheduler)
# ─────────────────────────────────────────────────────────────

# ---- Stage 1: build frontend (Vite) ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---- Stage 2: composer dependencies (prod) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist \
    --no-interaction --optimize-autoloader --ignore-platform-reqs

# ---- Stage 3: runtime ----
FROM php:8.2-fpm-alpine AS runtime
WORKDIR /var/www/html

# Ekstensi PHP + nginx + supervisor
RUN apk add --no-cache \
        nginx supervisor bash \
        icu-dev oniguruma-dev libzip-dev libpng-dev freetype-dev libjpeg-turbo-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring bcmath gd zip exif pcntl intl opcache \
    && apk del .build-deps

# Kode aplikasi + hasil build dari stage sebelumnya
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Konfigurasi runtime
COPY docker/php.ini          /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/nginx.conf       /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh    /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
               storage/logs storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]

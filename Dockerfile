# syntax=docker/dockerfile:1
FROM node:24.14.0-bookworm-slim@sha256:d8e448a56fc63242f70026718378bd4b00f8c82e78d20eefb199224a4d8e33d8 AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

FROM dunglas/frankenphp:1.11.3-php8.4-bookworm@sha256:17ac06e1d710e2bdac9893bd9efb6080b88d055f353bf3353c9503e3a61182df AS app
RUN apt-get update && apt-get install -y --no-install-recommends gosu unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_mysql mbstring zip opcache
COPY --from=composer:2.9.5@sha256:698d3801b2a622ace460c4743c781282fcbcb733a4cbf8b31c44731e846585e8 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-interaction --no-progress
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod 755 docker-entrypoint.sh
ENV APP_ENV=production APP_DEBUG=false PORT=8000 XDG_CONFIG_HOME=/tmp/caddy/config XDG_DATA_HOME=/tmp/caddy/data
EXPOSE 8000
ENTRYPOINT ["/app/docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/app/Caddyfile", "--adapter", "caddyfile"]

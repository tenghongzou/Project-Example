# 建置 context 為 repo 根目錄：
#   dev:  docker compose 使用（code 用 volume 掛載）
#   prod: CI 打包 UAT / prod 映像（code 打包進 image、no-dev、cache 預熱、worker mode）
FROM dunglas/frankenphp:1-php8.4 AS base

RUN install-php-extensions intl pdo_pgsql opcache zip redis opentelemetry

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ---------------------------------------------------------------- dev
FROM base AS dev

COPY docker/frankenphp/conf.d/app.ini /usr/local/etc/php/conf.d/app.ini

# ---------------------------------------------------------------- prod
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY docker/frankenphp/conf.d/app.prod.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/frankenphp/Caddyfile.prod /etc/frankenphp/Caddyfile

# 先只複製依賴描述檔，讓 vendor 層可以被 Docker layer cache 重用
COPY app/composer.json app/composer.lock app/symfony.lock /app/
RUN composer install --no-dev --no-scripts --prefer-dist --no-progress --no-interaction

COPY app /app

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
 && bin/console cache:warmup

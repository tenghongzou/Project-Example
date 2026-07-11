# 建置 context 為 repo 根目錄：
#   dev:  docker compose 使用（code 用 volume 掛載）
#   prod: CI 打包 UAT / prod 映像（code 打包進 image、no-dev、cache 預熱、worker mode）
FROM dunglas/frankenphp:1-php8.4 AS base

RUN install-php-extensions intl pdo_pgsql opcache zip redis opentelemetry grpc
# temporal/sdk 需要 sockets（RoadRunner Goridge relay）；獨立一層以保留上面 grpc 的建置快取
RUN install-php-extensions sockets

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ---------------------------------------------------------------- dev
FROM base AS dev

COPY docker/frankenphp/conf.d/app.ini /usr/local/etc/php/conf.d/app.ini

# ------------------------------------------------- temporal-worker
# Temporal PHP worker 跑在 RoadRunner 上（SDK 的標準宿主），code 同樣 volume 掛載
FROM dev AS temporal-worker

COPY --from=ghcr.io/roadrunner-server/roadrunner:2025.1.15 /usr/bin/rr /usr/local/bin/rr

CMD ["rr", "serve", "-c", ".rr.yaml"]

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
 && php bin/console cache:warmup

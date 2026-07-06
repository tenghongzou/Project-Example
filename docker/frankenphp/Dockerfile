FROM dunglas/frankenphp:1-php8.4

RUN install-php-extensions intl pdo_pgsql opcache zip redis opentelemetry

COPY conf.d/app.ini /usr/local/etc/php/conf.d/app.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

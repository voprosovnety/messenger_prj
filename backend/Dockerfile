FROM php:8.3-fpm-alpine

# Пакеты для Symfony + Composer
RUN apk add --no-cache \
    git unzip icu-dev libzip-dev postgresql-dev

RUN docker-php-ext-install intl zip pdo pdo_pgsql

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend
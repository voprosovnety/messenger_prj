FROM php:8.3-fpm-alpine

RUN apk add --no-cache git unzip icu-dev libzip-dev postgresql-dev openssl

RUN docker-php-ext-install intl zip pdo pdo_pgsql

RUN printf "upload_max_filesize=50M\npost_max_size=55M" \
    > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend/app

COPY app/ .

RUN composer install --optimize-autoloader --no-interaction --no-scripts

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

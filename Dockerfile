# PHP-FPM image for the app, the queue worker and the scheduler.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.4-fpm-alpine
RUN apk add --no-cache icu-libs libzip \
 && apk add --no-cache --virtual .build $PHPIZE_DEPS icu-dev libzip-dev linux-headers \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl sockets zip opcache pcntl \
 && apk del .build
COPY docker/php.ini /usr/local/etc/php/conf.d/el3ktron.ini
WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
USER www-data

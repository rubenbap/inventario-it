FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev --no-scripts

FROM serversideup/php:8.3-fpm-nginx

USER root
RUN install-php-extensions intl bcmath
USER www-data

COPY --chown=www-data:www-data --from=vendor /app /var/www/html

ENV AUTORUN_ENABLED=true \
    PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off

RUN php artisan package:discover --ansi

EXPOSE 8080

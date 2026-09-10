FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    git curl libzip-dev zip unzip oniguruma-dev icu-dev \
    && docker-php-ext-install pdo_mysql mbstring zip intl opcache bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-autoloader

COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]

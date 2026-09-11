FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    git curl libzip-dev zip unzip oniguruma-dev icu-dev su-exec \
    && docker-php-ext-install pdo_mysql mbstring zip intl opcache bcmath

# docker-compose points the queue, cache and session drivers at Redis, and
# REDIS_CLIENT defaults to phpredis, so the extension has to be in the image —
# running the Redis server alongside it isn't enough on its own.
# `yes ''` answers pecl's igbinary/lzf/zstd prompts with their defaults, which
# it would otherwise stall on for want of a tty.
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && yes '' | pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# The compose stack is a development environment — it bind-mounts source and
# seeds the database, and `php artisan db:seed` calls fake(), which lives in
# the dev-only fakerphp/faker. Compose sets this to true; the default keeps a
# lean image for anything that wants to build one for real.
ARG INSTALL_DEV=false

COPY composer.json composer.lock ./
RUN if [ "$INSTALL_DEV" = "true" ]; then         composer install --no-scripts --no-interaction --prefer-dist --no-autoloader;     else         composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-autoloader;     fi

COPY . .

# A working copy's bootstrap/cache/packages.php can list providers this image's
# vendor doesn't have. Discovery has to start from an empty manifest, or booting
# artisan to regenerate it fails first.
RUN rm -f bootstrap/cache/*.php \
    && composer dump-autoload --optimize \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

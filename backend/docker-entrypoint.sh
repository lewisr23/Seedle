#!/bin/sh
set -e

cd /var/www/html

# docker-compose bind-mounts the host checkout over this directory, so anything
# the image prepared inside it is only guaranteed on a fresh clone. Redo the
# parts that have to be true before the app can boot.

# .env is gitignored, so a fresh clone arrives without one.
if [ ! -f .env ]; then
    echo "[entrypoint] no .env found, seeding from .env.example"
    cp .env.example .env
fi

# An empty APP_KEY is a hard boot failure, not a warning.
if ! grep -qE '^APP_KEY=.+' .env; then
    echo "[entrypoint] generating APP_KEY"
    php artisan key:generate --force
fi

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# php-fpm keeps its root master process: it reopens /proc/self/fd/2 for its
# error log, which a de-privileged process gets EACCES on, and its pool config
# drops the workers to www-data on its own. Anything else (the queue worker)
# has no such requirement, so it runs unprivileged.
if [ "$1" = "php-fpm" ]; then
    exec "$@"
fi

exec su-exec www-data "$@"

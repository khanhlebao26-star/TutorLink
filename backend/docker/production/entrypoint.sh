#!/bin/sh
set -eu

if [ "${APP_ENV:-}" != "production" ] || [ "${APP_DEBUG:-}" != "false" ] || [ -z "${APP_KEY:-}" ]; then
    echo "Production requires APP_ENV=production, APP_DEBUG=false and APP_KEY." >&2
    exit 1
fi

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

exec docker-php-entrypoint "$@"

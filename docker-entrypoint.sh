#!/bin/sh
set -eu

# A newly mounted persistent disk is empty and owned by root. Initialize only
# Laravel's writable directories, then serve requests as the unprivileged user.
mkdir -p /app/storage/app/private /app/storage/framework/cache /app/storage/framework/sessions \
    /app/storage/framework/views /app/storage/logs /app/bootstrap/cache
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data /app/storage /app/bootstrap/cache
    exec gosu www-data "$0" "$@"
fi

# Secrets are resolved at runtime; database migrations belong in pre-deploy.
php artisan config:cache
exec "$@"

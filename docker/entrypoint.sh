#!/bin/sh
set -e

# Ensure Laravel storage and cache directory permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache 2>/dev/null || true

chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Optimize application for web service execution
if [ "$1" = "php-fpm" ]; then
    php artisan config:clear || true
    php artisan cache:clear || true
    php artisan route:clear || true
    php artisan view:clear || true
    php artisan optimize || true
fi

# Execute passed container command
exec "$@"

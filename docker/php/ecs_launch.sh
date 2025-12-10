#!/bin/bash

set -e

# Ensure storage directories exist with proper permissions
mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Run artisan commands as www-data to avoid permission issues
su -s /bin/sh www-data -c "php artisan event:cache"
su -s /bin/sh www-data -c "php artisan view:cache"
su -s /bin/sh www-data -c "php artisan config:cache"
su -s /bin/sh www-data -c "php artisan optimize"

# Wait for the migrations (still as root for DB access)
while true
do
    migrate_output=$(php artisan migrate --force --pretend)
    echo "$migrate_output"
    if [[ $migrate_output == *"Nothing to migrate"* ]]; then
        break;
    fi
done

# Process one time operations
su -s /bin/sh www-data -c "php artisan operations:process"

# Generate API documentation
su -s /bin/sh www-data -c "php artisan scramble:export"

su -s /bin/sh www-data -c "php artisan translations:auto-reconcile --all"

# Fix permissions one final time before starting php-fpm
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php-fpm

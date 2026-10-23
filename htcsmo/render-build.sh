#!/usr/bin/env bash
# Exit on error
set -o errexit

echo "--- Installing PHP Composer Dependencies ---"
composer install --no-dev --optimize-autoloader --no-interaction

echo "--- Verifying Stable Application Key ---"
if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY must be configured as a persistent Render environment variable."
    exit 1
fi

echo "--- Clearing & Caching Configurations ---"
php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "--- Linking Storage Folder ---"
php artisan storage:link || true

echo "--- Running Database Migrations ---"
php artisan migrate --force

echo "--- Deployment Build Complete! ---"

#!/bin/sh
set -eu

port="${PORT:-10000}"
sed -i "s/Listen 80/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/*:10000/*:${port}/" /etc/apache2/sites-available/000-default.conf

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY must be configured as a persistent environment variable."
    exit 1
fi

php artisan storage:link || true
php artisan view:clear
php artisan route:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# Keep scheduled deadline notifications running in the web-service container.
# The scheduler uses a shared cache lock, so horizontally scaled instances do
# not execute the same daily notification job more than once.
php artisan schedule:work >> storage/logs/scheduler.log 2>&1 &
php artisan queue:work --queue=mail,default --sleep=5 --tries=3 --timeout=30 >> storage/logs/queue.log 2>&1 &

exec apache2-foreground

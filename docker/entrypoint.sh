#!/bin/sh
set -e

cd /var/www/html

# Ensure Laravel runtime directories exist and are writable.
mkdir -p \
  storage/framework/views \
  storage/framework/cache \
  storage/framework/sessions \
  storage/logs \
  bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# Generate APP_KEY automatically for fresh environments.
if [ -f .env ] && ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force || true
fi

# Clear stale caches to avoid broken boot state.
php artisan optimize:clear || true

exec "$@"

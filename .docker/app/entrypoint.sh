#!/bin/sh
set -e

# Wait for DB to be healthy
echo "⏳ Waiting for database..."
until php bin/console doctrine:query:sql "SELECT 1" >/dev/null 2>&1; do
  sleep 2
done

# Run migrations automatically (safe for review/demo)
echo "🚀 Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

exec php-fpm

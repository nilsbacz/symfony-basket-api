#!/bin/sh
set -e

# Wait for database (simple static delay)
echo "⏳ Waiting 10 seconds for the database to be ready..."
sleep ${WAIT_FOR_DB_SECONDS:-10}
echo "✅ Continuing startup..."

# Run migrations automatically (safe for review/demo)
echo "🚀 Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

exec php-fpm

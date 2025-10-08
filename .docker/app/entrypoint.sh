#!/bin/sh
set -e

# Wait for database (simple static delay)
echo "⏳ Waiting 10 seconds for the database to be ready..."
sleep ${WAIT_FOR_DB_SECONDS:-10}
echo "✅ Continuing startup..."

# Run migrations automatically (safe for review/demo)
if [ "${AUTO_MIGRATE:-1}" = "1" ] && [ -f bin/console ]; then
  if php bin/console list doctrine:migrations:migrate >/dev/null 2>&1; then
    echo "🚀 Running migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction || true
  else
    echo "⚠️ Doctrine not installed yet, skipping migrations"
  fi
fi

exec php-fpm

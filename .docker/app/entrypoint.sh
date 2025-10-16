#!/bin/sh
set -e

# Wait for database (simple static delay)
echo "⏳ Waiting 10 seconds for the database to be ready..."
sleep ${WAIT_FOR_DB_SECONDS:-10}
echo "✅ Continuing startup..."

# install composer deps if missing
if [ ! -f /var/www/html/vendor/autoload.php ]; then
  echo "🔧 Installing Composer dependencies..."
  composer install --no-interaction --prefer-dist --no-progress
fi

# run migrations in dev if DB is up
php bin/console doctrine:migrations:migrate --no-interaction || true

exec php-fpm

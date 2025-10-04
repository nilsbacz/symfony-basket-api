# Multi-stage build for PHP-FPM with Composer and common Symfony extensions

# 1) Composer deps builder (uses composer.json to prebuild vendor)
FROM composer:2 AS composer_builder
WORKDIR /app
COPY composer.json composer.lock* symfony.lock* ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-progress --no-interaction

# 2) PHP runtime
FROM php:8.3-fpm-alpine AS runtime
WORKDIR /var/www/html

# --- System deps & PHP extensions ---
RUN apk add --no-cache bash git oniguruma-dev libzip-dev icu-dev \
  && docker-php-ext-install intl opcache pdo pdo_mysql mbstring zip

# Composer in runtime (handy for dev)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# PHP settings
COPY ./.docker/app/php.ini $PHP_INI_DIR/conf.d/php-custom.ini

# Copy vendor from builder first (owned by symfony)
COPY --chown=symfony:symfony --from=composer_builder /app/vendor /var/www/html/vendor

# Copy app code (excluding items filtered by .dockerignore) as symfony user
COPY --chown=symfony:symfony . /var/www/html

# Entrypoint (auto-migrate, waits for DB)
COPY --chown=symfony:symfony ./.docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER symfony
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# Multi-stage build for PHP-FPM with Composer and common Symfony extensions


# 1) Composer deps builder
FROM composer:2 AS composer_builder
WORKDIR /app
# Copy only composer manifests to leverage Docker layer caching
COPY composer.json composer.lock* symfony.lock* ./
# If composer.json doesn't exist yet, create a temp minimal one to avoid build fail
RUN if [ ! -f composer.json ]; then \
printf '{"name":"temp/temp","require":{}}' > composer.json; \
fi && \
composer install --no-dev --no-scripts --prefer-dist --no-progress --no-interaction || true


# 2) PHP runtime
FROM php:8.3-fpm-alpine AS runtime
WORKDIR /var/www/html


# System deps
RUN apk add --no-cache bash git icu-dev oniguruma-dev libzip-dev \
&& docker-php-ext-install intl opcache pdo pdo_mysql mbstring zip


# Opcache recommended settings
COPY ./.docker/app/php.ini $PHP_INI_DIR/conf.d/php-custom.ini


# Copy vendor from builder as a cache seed (will be overridden by volume in dev)
COPY --from=composer_builder /app/vendor /var/www/html/vendor


# Copy project (in dev we will mount the code; this allows image to run without volume too)
COPY . /var/www/html


# Create a non-root user (optional)
RUN addgroup -g 1000 symfony && adduser -D -G symfony -u 1000 symfony \
&& chown -R symfony:symfony /var/www/html


USER symfony
CMD ["php-fpm"]
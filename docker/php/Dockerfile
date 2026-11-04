FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    bash \
    su-exec

# Install PHP extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        intl \
        zip \
        opcache

# Copy php config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Get Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Entrypoint script for rights and migrations
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

# =============================================================================
# Stage 1: Node — build Vite/Vue 3 frontend assets
# =============================================================================
FROM node:20-alpine AS node-builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --prefer-offline

COPY vite.config.js ./
COPY resources/ resources/
COPY public/ public/

RUN npm run build

# =============================================================================
# Stage 2: PHP 8.3-FPM — production application image
# =============================================================================
FROM php:8.3-fpm-alpine AS php-base

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    icu-libs \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    supervisor \
    nginx \
    && rm -rf /var/cache/apk/*

# Install PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        bcmath \
        pcntl \
        gd \
        zip \
        intl \
        opcache \
        mbstring \
        xml \
        exif

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies (no dev, no scripts — scripts need full app)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Copy full application source
COPY . .

# Copy built frontend assets from node stage
COPY --from=node-builder /app/public/build public/build

# Run post-install scripts now that the full app is present
RUN composer run-script post-autoload-dump

# Set correct permissions on writable directories
RUN chown -R www-data:www-data \
        storage \
        bootstrap/cache \
    && chmod -R 775 \
        storage \
        bootstrap/cache

# PHP-FPM configuration: listen on TCP socket for nginx proxy
RUN sed -i 's|listen = /run/php/php-fpm.sock|listen = 0.0.0.0:9000|g' \
        /usr/local/etc/php-fpm.d/www.conf \
    || true

# Copy PHP production ini
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

EXPOSE 9000

CMD ["php-fpm"]

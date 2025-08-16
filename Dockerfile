# Multi-stage build for TravelBot Symfony application

# =============================================================================
# Stage 1: PHP Dependencies
# =============================================================================
FROM php:8.3-fpm-alpine as php-dependencies

# Install system dependencies
RUN apk add --no-cache \
    curl \
    git \
    icu-dev \
    libpng-dev \
    libxml2-dev \
    oniguruma-dev \
    postgresql-dev \
    unzip \
    zip

# Install PHP extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        intl \
        mbstring \
        opcache \
        pdo \
        pdo_pgsql \
        xml

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# =============================================================================
# Stage 2: Build assets with Node.js
# =============================================================================
FROM node:22-bookworm as asset-builder

WORKDIR /app

# Copy package files
COPY package.json package-lock.json* ./

# Install dependencies (including dev deps for building)
RUN npm ci

# Copy vendor directory from PHP dependencies stage for Symfony UX assets
COPY --from=php-dependencies /var/www/html/vendor ./vendor

# Copy source files needed for build
COPY assets/ ./assets/
COPY templates/ ./templates/
COPY src/ ./src/
COPY webpack.config.js ./
COPY tailwind.config.js* ./
COPY postcss.config.js* ./

# Build production assets
RUN npm run build

# =============================================================================
# Stage 3: PHP-only for Docker Compose
# =============================================================================
FROM php:8.3-fpm-alpine as production-php-only

# Install system dependencies
RUN apk add --no-cache \
    curl \
    git \
    icu-dev \
    libpng-dev \
    libxml2-dev \
    oniguruma-dev \
    postgresql-dev \
    unzip \
    zip

# Install PHP extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        intl \
        mbstring \
        opcache \
        pdo \
        pdo_pgsql \
        xml

# Configure PHP for production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy vendor directory from php-dependencies stage
COPY --from=php-dependencies /var/www/html/vendor ./vendor

# Copy application source code
COPY . .

# Copy built assets from the asset-builder stage
COPY --from=asset-builder /app/public/build ./public/build

# Set production environment
ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV APP_RUNTIME_ENV=prod
ENV XDEBUG_MODE=off

# Create cache directory and set permissions
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && chmod -R 775 var

# Warm up Symfony cache for production (with dummy DATABASE_URL for build)
RUN DATABASE_URL="postgresql://dummy:dummy@localhost:5432/dummy" \
    php bin/console cache:clear --env=prod --no-debug \
    && DATABASE_URL="postgresql://dummy:dummy@localhost:5432/dummy" \
    php bin/console cache:warmup --env=prod --no-debug

# Final permissions fix
RUN chown -R www-data:www-data /var/www/html

# Switch to www-data user
USER www-data

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]

# =============================================================================
# Stage 4: Production with nginx + supervisor for ECS
# =============================================================================
FROM php:8.3-fpm-alpine as production

# Install system dependencies
RUN apk add --no-cache \
    curl \
    git \
    icu-dev \
    libpng-dev \
    libxml2-dev \
    nginx \
    oniguruma-dev \
    postgresql-dev \
    supervisor \
    unzip \
    zip

# Install PHP extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        intl \
        mbstring \
        opcache \
        pdo \
        pdo_pgsql \
        xml

# Configure PHP for production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy vendor directory from php-dependencies stage
COPY --from=php-dependencies /var/www/html/vendor ./vendor

# Copy application source code
COPY . .

# Copy built assets from the asset-builder stage
COPY --from=asset-builder /app/public/build ./public/build

# Set production environment
ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV APP_RUNTIME_ENV=prod
ENV XDEBUG_MODE=off

# Create cache directory and set permissions
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && chmod -R 775 var

# Warm up Symfony cache for production (with dummy DATABASE_URL for build)
RUN DATABASE_URL="postgresql://dummy:dummy@localhost:5432/dummy" \
    php bin/console cache:clear --env=prod --no-debug \
    && DATABASE_URL="postgresql://dummy:dummy@localhost:5432/dummy" \
    php bin/console cache:warmup --env=prod --no-debug

# Configure nginx
RUN rm /etc/nginx/http.d/default.conf
COPY docker/nginx-ecs.conf /etc/nginx/http.d/default.conf

# Create supervisor configuration and log directory
RUN mkdir -p /etc/supervisor/conf.d /var/log/supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Final permissions fix
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 for nginx
EXPOSE 80

# Start supervisor (manages nginx + php-fpm)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# =============================================================================
# Stage 4: Nginx with static assets
# =============================================================================
FROM nginx:alpine as nginx

# Copy built assets from asset-builder stage
COPY --from=asset-builder /app/public/build /var/www/html/public/build

# Copy static files that don't need building
COPY public/ /var/www/html/public/

# Remove default nginx configuration and copy our custom config
RUN rm /etc/nginx/conf.d/default.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/conf.d/default.conf
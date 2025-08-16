# Single-stage build using PHP-FPM with Nginx
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-dev \
    icu-dev \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        intl \
        opcache \
        mbstring \
        xml

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Set APP_ENV to prod before copying application code
ENV APP_ENV=prod

# Copy application code
COPY . .

# Create minimal .env file for production
RUN echo "APP_ENV=prod" > .env

# Optimize autoloader
RUN composer dump-autoload --optimize --no-dev

# Copy configuration files
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create necessary directories and fix permissions
RUN mkdir -p /var/www/html/var/cache /var/www/html/var/log /var/log/supervisor \
    && chown -R www-data:www-data /var/www/html/var \
    && chmod -R 775 /var/www/html/var \
    && chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Use supervisor to run both nginx and php-fpm
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
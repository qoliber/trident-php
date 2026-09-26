FROM php:8.3-fpm-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Install PHP extensions for testing
RUN docker-php-ext-install opcache

# Configure PHP-FPM
RUN echo "php_admin_value[error_reporting] = E_ALL" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "php_admin_flag[display_errors] = on" >> /usr/local/etc/php-fpm.d/www.conf

# Create app directory
RUN mkdir -p /var/www/html /var/www/library /var/www/tests

WORKDIR /var/www

# Install dependencies (will be mounted)
CMD ["sh", "-c", "composer install --no-interaction 2>/dev/null || true && php-fpm"]

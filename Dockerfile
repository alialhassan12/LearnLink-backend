FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    git

# Install PHP extensions required by Laravel, PostgreSQL, and Reverb (pcntl)
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    xml \
    zip \
    bcmath \
    pcntl

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy the application code
COPY . .

# Install PHP production dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions for Laravel directories
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]

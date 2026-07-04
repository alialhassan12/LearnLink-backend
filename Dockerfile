FROM php:8.4-fpm-alpine

# Install system dependencies (including Nginx)
RUN apk add --no-cache \
    postgresql-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    nginx

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

# Copy Nginx config and configure Nginx directory / user permissions
RUN cp nginx.conf /etc/nginx/http.d/default.conf \
    && mkdir -p /run/nginx \
    && sed -i 's/user nginx;/user www-data;/g' /etc/nginx/nginx.conf

# Copy PHP configurations
COPY php-uploads.ini /usr/local/etc/php/conf.d/

# Configure startup script executable permission
RUN chmod +x /var/www/start.sh

# Expose HTTP port 80 (detected by Render)
EXPOSE 80

CMD ["/var/www/start.sh"]

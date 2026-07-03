#!/bin/sh

# Run database migrations automatically on startup
echo "Running migrations..."
php artisan migrate --force

# Start PHP-FPM in the background (-D daemonizes it)
echo "Starting PHP-FPM..."
php-fpm -D

# Start Laravel Reverb WebSocket server in the background
echo "Starting Laravel Reverb..."
php artisan reverb:start --host=0.0.0.0 --port=8080 &

# Start the Laravel queue worker to handle background jobs (e.g. emails)
echo "Starting Laravel Queue Worker..."
php artisan queue:work --sleep=3 --tries=3 &

# Start the Laravel Scheduler runner in the background (runs cron tasks every minute)
echo "Starting Laravel Scheduler..."
php artisan schedule:work &

# Start Nginx in the foreground to keep the Docker container running
echo "Starting Nginx..."
nginx -g "daemon off;"

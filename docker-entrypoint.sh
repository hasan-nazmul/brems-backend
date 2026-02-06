# #!/bin/bash
# set -e

# echo "Starting Apache..."

# # Try to migrate, but don't crash if it fails
# echo "Attempting migration..."
# php artisan migrate --force || echo "Migration failed, but continuing..."

# # Start the server no matter what
# exec apache2-foreground

#!/bin/bash
set -e

echo "==========================================="
echo "Starting Application..."
echo "==========================================="

# Wait for database to be ready (optional but recommended)
echo "Waiting for database connection..."
max_tries=30
counter=0
until php artisan db:monitor --databases=mysql > /dev/null 2>&1 || [ $counter -eq $max_tries ]; do
    echo "Waiting for database... (attempt $((counter+1))/$max_tries)"
    sleep 2
    counter=$((counter+1))
done

if [ $counter -eq $max_tries ]; then
    echo "Warning: Database connection timeout, continuing anyway..."
fi

# Clear and cache config for production
echo "Optimizing application..."
php artisan config:cache || echo "Config cache failed, continuing..."
php artisan route:cache || echo "Route cache failed, continuing..."
php artisan view:cache || echo "View cache failed, continuing..."

# Run migrations
echo "==========================================="
echo "Running database migrations..."
echo "==========================================="
if php artisan migrate --force; then
    echo "✓ Migrations completed successfully"
else
    echo "⚠ Migration failed, but continuing..."
    echo "You may need to run migrations manually:"
    echo "  php artisan migrate --force"
fi

# Optional: Run seeders only if specific flag/env is set
if [ "$RUN_SEEDERS" = "true" ]; then
    echo "Running database seeders..."
    php artisan db:seed --force || echo "Seeding failed, continuing..."
fi

# Create storage link if not exists
echo "Checking storage link..."
php artisan storage:link 2>/dev/null || echo "Storage link already exists or failed"

# Set proper permissions
echo "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

echo "==========================================="
echo "Starting Apache..."
echo "==========================================="

# Start Apache
exec apache2-foreground
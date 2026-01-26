# 1. Use PHP 8.4 to match your composer.lock requirements
FROM php:8.4-apache

# 2. Install dependencies
# We include git, zip, and unzip for Composer
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo_mysql zip

# 3. Enable Apache mod_rewrite
RUN a2enmod rewrite

# 4. Set working directory
WORKDIR /var/www/html

# 5. Copy application files
COPY . .

# 6. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# 7. Set permissions
# We give ownership of the whole HTML folder to www-data.
RUN chown -R www-data:www-data /var/www/html

# 8. Configure Apache DocumentRoot to point to public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

# --- THE FIX ---
# Correct path (no .0 at the end)
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# 9. Expose Port
EXPOSE 80
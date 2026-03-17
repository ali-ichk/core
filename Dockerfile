FROM php:8.3-apache

# 1. Install System Dependencies
RUN apt-get update && apt-get install -y \
    unzip \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    gettext \
    && rm -rf /var/lib/apt/lists/*

# 2. Install PHP Extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
    mysqli \
    pdo_mysql \
    gd \
    zip \
    intl \
    xml \
    gettext \
    bcmath \
    curl \
    mbstring

# Enable Apache mod_rewrite
RUN a2enmod rewrite \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# 3. Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 4. Copy application source from local context
COPY . .

# 5. Install PHP Dependencies
RUN composer install --no-dev --optimize-autoloader

# 6. Configure PHP for Gibbon
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/gibbon.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/gibbon.ini \
    && echo "max_input_vars = 8000" >> /usr/local/etc/php/conf.d/gibbon.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/gibbon.ini \
    && echo "max_file_uploads = 20" >> /usr/local/etc/php/conf.d/gibbon.ini \
    && echo "allow_url_fopen = On" >> /usr/local/etc/php/conf.d/gibbon.ini \
    && echo "session.gc_maxlifetime = 1200" >> /usr/local/etc/php/conf.d/gibbon.ini

# 7. Permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80

CMD ["apache2-foreground"]
FROM php:8.2-apache

# 1. Install System Dependencies (Minimal required for Gibbon)
# TODO: Pin version in apt get install 
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
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
    bcmath

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# 3. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 4. Clone Gibbon v31 (Latest Stable)
RUN git clone --depth 1 --branch v31.0.00 https://github.com/GibbonEdu/core.git . \
    && rm -rf .git

# 5. Install PHP Dependencies
RUN composer install --no-dev --optimize-autoloader

# 6. Configure PHP for Gibbon (Upload limits, etc.)
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/gibbon.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/gibbon.ini \
    && echo "max_input_vars = 5000" >> /usr/local/etc/php/conf.d/gibbon.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/gibbon.ini

# 7. Permissions (Crucial for the Web Installer to work)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

CMD ["apache2-foreground"]

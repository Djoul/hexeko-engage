
# Base PHP image with FPM
FROM php:8.4.10-fpm

# Build arguments for setting user and group IDs
ARG UID=1000
ARG GID=1000

# Output UID and GID for debugging during build
RUN echo "Building with UID=${UID} and GID=${GID}"

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libpq-dev \
    postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    zip \
    xml

# Install Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Install Xdebug extension via PECL
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Safely modify www-data UID and GID if needed
RUN if getent group ${GID}; then echo "Group ID ${GID} already exists"; else groupmod -g ${GID} www-data; fi \
 && if id -u ${UID} >/dev/null 2>&1; then echo "User ID ${UID} already exists"; else usermod -u ${UID} www-data; fi

# Copy Composer from the official Composer image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory
WORKDIR /var/www/html

# Create necessary directories and set permissions
RUN mkdir -p /var/www/.config/psysh /var/www/.composer \
 && chown -R www-data:www-data /var/www/.config /var/www/.composer \
 && chmod -R 755 /var/www/.config /var/www/.composer

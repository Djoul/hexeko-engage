FROM node:24.5.0-alpine3.21 AS frontend-builder

WORKDIR /app

COPY . .

RUN npm ci

RUN npm run build

FROM php:8.4.10-fpm

ARG APP_VERSION=0.0.0-development

WORKDIR /var/www/html

# Install dependencies and PHP extensions
RUN apt-get update && apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libpq-dev
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo_pgsql pgsql zip exif pcntl bcmath sockets exif gd
RUN pecl install excimer && docker-php-ext-enable excimer
RUN pecl install redis-6.2.0 && docker-php-ext-enable redis
RUN pecl install opentelemetry && docker-php-ext-enable opentelemetry

# Install composer
COPY --from=composer:2.7.9 /usr/bin/composer /usr/local/bin/composer

# Copy project files
COPY ../.. .

COPY --from=frontend-builder /app/public/build public/build

# Install Composer dependencies
RUN composer install

# Set proper permissions AFTER composer install to ensure they persist
RUN chown -R www-data:www-data storage/ bootstrap/cache/
RUN chmod -R 775 storage/ bootstrap/cache/

# Configure php.ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN sed -i 's/post_max_size = 8M/post_max_size = 30M/g' "$PHP_INI_DIR/php.ini"
RUN sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 30M/g' "$PHP_INI_DIR/php.ini"
RUN sed -i 's/memory_limit = 128M/memory_limit = 512M/g' "$PHP_INI_DIR/php.ini"

# This config prevents php-fpm from displaying logs for each request
RUN echo "access.log = /dev/null" >> /usr/local/etc/php-fpm.d/www.conf

ENV SENTRY_RELEASE $APP_VERSION

CMD ["./docker/php/ecs_launch.sh"]

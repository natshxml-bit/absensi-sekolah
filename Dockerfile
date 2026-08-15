FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libonig-dev libxml2-dev libcurl4-openssl-dev \
    unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mbstring zip bcmath gd pcntl exif \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs --no-scripts
COPY . .
RUN php artisan package:discover --ansi \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

EXPOSE 80

CMD ["sh", "-c", "php artisan migrate --force && echo 'MIGRATIONS OK' && php artisan serve --host=0.0.0.0 --port=${PORT:-80} 2>&1"]

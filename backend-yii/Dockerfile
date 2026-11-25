FROM composer:2 AS composer_bin
FROM php:8.3-cli
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libonig-dev unzip git \
    && docker-php-ext-install pdo_pgsql mbstring && rm -rf /var/lib/apt/lists/*
COPY --from=composer_bin /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY backend-yii/ /app/
COPY database/schema.sql /app/database/schema.sql
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader \
    && mkdir -p runtime && chown -R www-data:www-data runtime
USER www-data
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "web", "web/index.php"]

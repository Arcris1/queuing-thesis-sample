# Smart Queue — Laravel 13 API (PHP 8.4)
FROM php:8.4-cli-bookworm

# System deps + PHP extensions needed by the app (Postgres, zip, bcmath, intl, pcntl for queue/reverb)
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql zip bcmath intl pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP deps first (better layer caching). Keep dev deps: seeders/factories use Faker.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist

# App source
COPY . .
RUN composer dump-autoload --optimize \
    && chmod +x docker-entrypoint.sh \
    && chmod -R ug+rw storage bootstrap/cache

EXPOSE 8000
ENTRYPOINT ["./docker-entrypoint.sh"]

FROM node:22-bookworm-slim AS assets

WORKDIR /app
ENV VITE_WAYFINDER_COMMAND=true
COPY package.json package-lock.json ./
RUN npm ci
COPY resources resources
COPY vite.config.ts tsconfig.json components.json ./
COPY app app
COPY bootstrap bootstrap
COPY config config
COPY public public
COPY routes routes
COPY composer.json composer.lock ./
RUN npm run build

FROM php:8.5-cli-bookworm AS runtime

ENV APP_ENV=production \
    LOG_CHANNEL=stderr \
    PORT=8080

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libpq-dev libzip-dev unzip \
    && docker-php-ext-install -j1 bcmath intl opcache pcntl pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.save_comments=1'; \
    } > /usr/local/etc/php/conf.d/opcache-production.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

COPY . .
COPY --from=assets /app/public/build public/build

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 8080

CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan serve --host=0.0.0.0 --port=${PORT}"]

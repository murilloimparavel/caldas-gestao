FROM node:22-bookworm-slim AS assets

ARG WAYFINDER_SKIP_GENERATE=false
ENV WAYFINDER_SKIP_GENERATE=${WAYFINDER_SKIP_GENERATE}

WORKDIR /app
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
    && docker-php-ext-install -j"$(nproc)" bcmath intl opcache pcntl pdo_pgsql zip \
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
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

COPY . .
RUN composer dump-autoload --no-dev --no-interaction --optimize
COPY --from=assets /app/public/build public/build

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 8080

CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan view:cache && case \"${CALDAS_PROCESS:-web}\" in web) exec php artisan serve --host=0.0.0.0 --port=${PORT} ;; worker) exec php artisan queue:work --sleep=1 --tries=3 --max-time=3600 ;; scheduler) exec php artisan schedule:work ;; *) echo \"Unknown CALDAS_PROCESS: ${CALDAS_PROCESS}\" >&2; exit 1 ;; esac"]

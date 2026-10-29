#!/bin/sh
set -eu
umask 077
mkdir -p "$HOME" storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
[ -f .env ] || cp .env.example .env
# No authentication volume or API-key environment is present during dependency scripts.
composer install --no-interaction --no-progress --prefer-dist --no-scripts
composer dump-autoload --no-interaction --no-scripts
php artisan package:discover --ansi
php artisan key:generate --force --no-interaction
npm ci --ignore-scripts
npm run build

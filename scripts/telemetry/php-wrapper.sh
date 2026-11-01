#!/bin/sh
set -eu

# Laravel's development server drops externally supplied test environment values
# when reload is enabled and an .env file exists. Keep this limited to the
# disposable testing environment so the server uses the same SQLite fixture.
if [ "${APP_ENV:-}" = testing ] && [ "${1:-}" = artisan ] && [ "${2:-}" = serve ]; then
    for argument in "$@"; do
        if [ "$argument" = --no-reload ]; then
            exec /usr/local/bin/php "$@"
        fi
    done
    exec /usr/local/bin/php "$@" --no-reload
fi

exec /usr/local/bin/php "$@"

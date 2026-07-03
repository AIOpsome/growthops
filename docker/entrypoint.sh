#!/bin/sh
set -e

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=.\+' .env; then
    php artisan key:generate --force
fi

touch database/database.sqlite
php artisan migrate --force --seed

exec php artisan serve --host=0.0.0.0 --port=8000

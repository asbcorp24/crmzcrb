#!/usr/bin/env sh
set -e
cd "$(dirname "$0")"
[ -f .env ] || cp .env.example .env
[ -f database/database.sqlite ] || touch database/database.sqlite
composer install --no-interaction --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
chmod -R ug+rw storage bootstrap/cache || true
echo "Portal installed. Point web server DocumentRoot to: $(pwd)/public"

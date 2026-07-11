#!/bin/bash
set -e

php artisan migrate --force
php artisan db:seed --class=PackageSeeder --force
php artisan serve --host=0.0.0.0 --port=${PORT:-8000}

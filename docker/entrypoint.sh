#!/bin/sh
set -eu

mkdir -p /var/www/html/var /var/www/html/public/uploads
chown -R www-data:www-data /var/www/html/var /var/www/html/public/uploads

exec docker-php-entrypoint "$@"

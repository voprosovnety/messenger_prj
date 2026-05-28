#!/bin/sh
set -e

echo "[entrypoint] Clearing and warming up cache..."
php bin/console cache:clear --env=${APP_ENV:-prod}
mkdir -p /var/www/uploads
chown -R www-data:www-data var/ /var/www/uploads

echo "[entrypoint] Waiting for PostgreSQL..."
until php -r "new PDO('pgsql:host=postgres;dbname=messenger', 'messenger', 'messenger');" 2>/dev/null; do
  sleep 2
done
echo "[entrypoint] PostgreSQL is ready."

if [ ! -f config/jwt/private.pem ]; then
  echo "[entrypoint] Generating JWT keypair..."
  php bin/console lexik:jwt:generate-keypair
fi

echo "[entrypoint] Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec "$@"

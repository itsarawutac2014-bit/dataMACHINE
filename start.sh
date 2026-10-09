#!/bin/bash
set -e

PORT="${PORT:-80}"
echo "[Railway Start] Configuring Apache to listen on port ${PORT}..."

sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g" /etc/apache2/sites-available/000-default.conf
echo "ServerName localhost" >> /etc/apache2/apache2.conf

if [ -n "$DB_HOST" ]; then
    echo "[Railway Start] Checking database connection..."
    for i in {1..10}; do
        if php /var/www/html/init_db.php; then
            break
        fi
        echo "[Railway Start] Database not ready yet, waiting 3s (attempt $i/10)..."
        sleep 3
    done
fi

echo "[Railway Start] Starting Apache web server..."
exec apache2-foreground

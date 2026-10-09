FROM php:8.2-apache

# ติดตั้ง PHP Extensions ที่จำเป็น
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql dom mbstring curl \
    && a2enmod rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

# ตั้งค่า Working Directory
WORKDIR /var/www/html

# คัดลอกโค้ดทั้งหมดเข้า Container
COPY . /var/www/html/

# ตั้งค่าสิทธิ์โฟลเดอร์สำหรับ Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# กำหนด Port
EXPOSE 80

CMD ["sh", "-c", "sed -i 's/Listen 80/Listen '${PORT:-80}'/g' /etc/apache2/ports.conf && sed -i 's/<VirtualHost \\*:80>/<VirtualHost \\*:'${PORT:-80}'>/g' /etc/apache2/sites-available/000-default.conf && (php /var/www/html/init_db.php || true) && exec apache2-foreground"]

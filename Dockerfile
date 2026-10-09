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
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost _default_:*>/g' /etc/apache2/sites-available/000-default.conf \
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

CMD ["sh", "-c", "if [ -n \"$PORT\" ] && [ \"$PORT\" != \"80\" ]; then echo \"Listen $PORT\" >> /etc/apache2/ports.conf; fi && (php /var/www/html/init_db.php &) && exec apache2-foreground"]

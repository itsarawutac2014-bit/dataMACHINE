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
    && rm -rf /var/lib/apt/lists/*

# ตั้งค่า Working Directory
WORKDIR /var/www/html

# คัดลอกโค้ดทั้งหมดเข้า Container
COPY . /var/www/html/

# ตั้งค่าสิทธิ์โฟลเดอร์สำหรับ Apache และ start.sh
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod +x /var/www/html/start.sh

# กำหนด Port
EXPOSE 80

CMD ["/bin/bash", "/var/www/html/start.sh"]

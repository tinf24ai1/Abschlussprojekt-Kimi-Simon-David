FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli && docker-php-ext-enable pdo_mysql mysqli

WORKDIR /var/www/html

# Copy application files
COPY display_table.php .
COPY style.css .
COPY index.php .
# Expose port 80 (Apache's default)
EXPOSE 80

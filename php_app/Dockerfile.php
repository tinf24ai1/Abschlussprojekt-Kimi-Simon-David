FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli && docker-php-ext-enable pdo_mysql mysqli

WORKDIR /var/www/html

# Copy application files
COPY index.php .
COPY display_table.php .

# Copy the new subdirectories and their contents
COPY css/ css/
COPY js/ js/
COPY include/ include/

# Expose port 80 (Apache's default)
EXPOSE 80

FROM php:8.2-apache

# Install PDO MySQL extension and mysqli (mysqli can be useful for some tools/checks)
RUN docker-php-ext-install pdo pdo_mysql mysqli && docker-php-ext-enable pdo_mysql mysqli

# Optional: Install common PHP utilities like git, zip, etc. if needed by your app later
# RUN apt-get update && apt-get install -y \
#       git \
#       zip \
#     && rm -rf /var/lib/apt/lists/*

# Set working directory (Apache's default document root)
WORKDIR /var/www/html

# Copy application files
# These files will be served by Apache
COPY display_table.php .
COPY style.css .

# Expose port 80 (Apache's default)
EXPOSE 80

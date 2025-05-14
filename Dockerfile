# Use an official PHP image with Apache
FROM php:8.2-apache 

# Install system dependencies that might be needed by some PHP extensions
# (mysqli and pdo_mysql often need libmariadb-dev or default-libmysqlclient-dev)
# For Debian-based images (like php:apache):
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    # Add other dependencies if needed by your extensions
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd # Example for GD library, if you need image processing

# Install PHP extensions: pdo_mysql is CRUCIAL for MySQL with PDO
# You can also install mysqli if your code uses that directly (though PDO is generally preferred)
RUN docker-php-ext-install pdo pdo_mysql mysqli # Ensure pdo itself is also enabled

# Set the working directory for Apache's web root
WORKDIR /var/www/html

# Copy the contents of the 'modular_system' directory
# from your project into the web root in the container.
# Also, set the correct ownership for Apache.
COPY --chown=www-data:www-data ./modular_system/ .

# Ensure mod_rewrite is enabled if you use .htaccess with RewriteRules.
RUN a2enmod rewrite

# Expose port 80 for Apache
EXPOSE 80

# The default CMD for php:apache images usually starts Apache correctly.

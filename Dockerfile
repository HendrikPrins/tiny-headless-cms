# php/Dockerfile
FROM php:8.4-fpm

# Install extensions as needed
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Set working directory
WORKDIR /var/www/html

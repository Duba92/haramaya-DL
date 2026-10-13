FROM php:8.2-apache

# Install MySQL extensions for PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy your application files into the container
COPY . /var/www/html/

# Set the working directory
WORKDIR /var/www/html

# Expose port 80 for Apache
EXPOSE 80

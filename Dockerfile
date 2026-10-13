FROM php:8.2-apache

# Install MySQL extensions for PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy your application files into the container
COPY . /var/www/html/

# Set the working directory
WORKDIR /var/www/html

# Expose port 80 for Apache
EXPOSE 80
# ... your existing setup ...

# Configure Apache to listen on the port provided by Render
RUN sed -i "s/Listen 80/Listen ${PORT:-10000}/g" /etc/apache2/ports.conf

# Expose the default Render port (for documentation, not strictly required)
EXPOSE 10000

# Start Apache in the foreground
CMD ["apache2-foreground"]

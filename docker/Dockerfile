FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html
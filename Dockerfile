FROM php:7.0-apache

COPY / /var/www/html/
WORKDIR /var/www/html/

# Composer
RUN apt-get update
RUN apt-get install -y curl nano git zip
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Memcached
RUN apt-get update && apt-get install -y libz-dev libmemcached-dev
RUN pecl install memcached
RUN echo extension=memcached.so >> /usr/local/etc/php/conf.d/memcached.ini

# APCU
RUN apt-get update && apt-get install -y
RUN docker-php-ext-install opcache
RUN pecl install apcu-5.1.5
RUN docker-php-ext-enable apcu

# Install dependencies
RUN composer install --no-interaction
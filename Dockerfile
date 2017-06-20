FROM php:7.0-apache

# Copy whole project
# Copy parameters.docker.yml into parameters.yml
COPY / /var/www/html/
COPY config/parameters.docker.yml /var/www/html/config/parameters.yml
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
RUN { \
		echo 'apc.enabled=1'; \
		echo 'apc.enable_cli=1'; \
	} >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini

# Install dependencies
RUN composer install --no-interaction

FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip \
    unzip \
    git \
    curl \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev

RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    pdo_mysql \
    mbstring \
    zip \
    exif \
    pcntl

RUN pecl install redis && docker-php-ext-enable redis

WORKDIR /var/www
FROM php:8.4-fpm-alpine

# Установка необходимых расширений PHP
RUN apk add --no-cache $PHPIZE_DEPS \
    && apk add --no-cache git zip unzip libzip-dev mysql-client icu-dev \
    && docker-php-ext-install pdo pdo_mysql zip intl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Увеличение лимитов PHP для больших проектов
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory-limit.ini

CMD ["php-fpm"]

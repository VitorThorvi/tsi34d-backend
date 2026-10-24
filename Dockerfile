FROM php:8.3.4-fpm

RUN docker-php-ext-install pdo pdo_mysql
RUN docker-php-ext-enable pdo_mysql

RUN apt-get update -y && apt-get install -y libzip-dev zip
RUN docker-php-ext-install zip

# Fuso do PHP (web, CLI e testes) em Brasília; o padrão da imagem é UTC.
RUN printf 'date.timezone = America/Sao_Paulo\n' > /usr/local/etc/php/conf.d/timezone.ini

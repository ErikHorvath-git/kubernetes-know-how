FROM php:8.2-apache

# pdo_mysql musíme doinštalovať — base image ho nemá
RUN docker-php-ext-install pdo pdo_mysql

COPY src-be/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

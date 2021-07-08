FROM php:7.0-apache
COPY . /var/www/html
#COPY config/* /usr/local/etc/php/
RUN docker-php-ext-install mysqli
EXPOSE 80

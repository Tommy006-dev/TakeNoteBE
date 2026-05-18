FROM richarvey/nginx-php-fpm:latest

COPY . /var/www/html

ENV WEBROOT /var/www/html/public
ENV COMPOSER_ALLOW_SUPERUSER 1

RUN composer install no-dev optimize-autoloader

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
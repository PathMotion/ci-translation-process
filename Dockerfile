FROM php:7.3-alpine3.9


RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer && composer global require hirak/prestissimo --no-plugins --no-scripts
WORKDIR /app
COPY . ./
RUN composer install --no-dev --no-interaction -o

ENTRYPOINT ["/usr/local/bin/php", "/app/index.php"]
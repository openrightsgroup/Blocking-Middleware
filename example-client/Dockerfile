
FROM php:7

ARG DEPLOYMENT=development

RUN mkdir /var/www/example-client


COPY ./ /var/www/example-client
COPY credentials.docker.php /var/www/example-client/credentials.php

RUN mv "$PHP_INI_DIR/php.ini-$DEPLOYMENT" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www


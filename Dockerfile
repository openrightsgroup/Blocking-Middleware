
FROM php:7.3

ARG DEPLOYMENT=development

RUN mkdir /var/www/api

RUN apt update && \
    apt install -y librabbitmq-dev libpq-dev amqp-tools python && \
    apt clean

RUN pecl install amqp redis && \
    docker-php-ext-enable amqp redis && \
    docker-php-ext-install pgsql pdo_pgsql json 

ADD api/1.2 /var/www/api/1.2

COPY api/1.2/libs/config.docker.php /var/www/api/1.2/libs/config.php

ADD backend /var/www/backend
ADD config /var/www/config

RUN mv "$PHP_INI_DIR/php.ini-$DEPLOYMENT" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/api


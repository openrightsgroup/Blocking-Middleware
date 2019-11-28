
FROM php:7

RUN mkdir /var/www/api
ADD api/1.2 /var/www/api/1.2

COPY api/1.2/libs/config.docker.php /var/www/api/1.2/libs/config.php

ADD backend /var/www/backend
WORKDIR /var/www/api


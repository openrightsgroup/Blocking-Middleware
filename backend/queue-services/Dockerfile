
FROM python:2.7

RUN apt-get update && \
    apt-get -y install whois && \
    apt-get clean

RUN mkdir /srv/queue-service
COPY requirements.txt /srv/queue-service/
RUN pip install -r /srv/queue-service/requirements.txt

COPY *.py /srv/queue-service/
COPY config.docker.ini /srv/queue-service/config.ini

WORKDIR /srv/queue-service


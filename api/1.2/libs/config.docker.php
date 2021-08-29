<?php


/* Postgres database config */

$PG_HOST = 'db';
$PG_PORT = 5432;
$PG_USER = 'blocked';
$PG_PASS = 'blocked';
$PG_DB = 'blocked';

/* AMQP Config */

$AMQP_HOST = 'amqp';
$AMQP_USER = 'blocked';
$AMQP_PASS = 'blocked';
$AMQP_VHOST= '/';

$SUBMIT_ROUTING_KEY = 'check.org'; # submit via robots.txt checker
# $SUBMIT_ROUTING_KEY = 'url.org'; # submit directly to ISP queues

define('SITE_EMAIL', 'blocked@example.com');
define('SITE_NAME', 'Example.com');
define('SITE_URL', 'https://www.example.com');
define('CONFIRM_URL', 'https://www.example.com/confirm');
define('VERIFY_URL', 'https://www.example.com/verify');

define('FEATURE_SEND_SUBSCRIBE_EMAIL', false); # set to true to enable mail sending

define('AMQP_PUBLIC_QUEUE_TIMEOUT', 4*86400*1000); # queued jobs expire after 4 days (in milliseconds);

define('REDIS', 'localhost:6379');

$ELASTIC = "http://localhost:9200";

define('ELASTIC_ADULT_FILTER', 'escort~ OR fetish OR stripper~ OR porn~ OR blowjob~ OR femdom~ OR fuck~');

$REQUEUE_EXCLUDE_SOURCES = array();

#define('AWS_DYNAMODB_ACCESS_KEY', 'asdasd');
#define('AWS_DYNAMODB_SECRET_KEY', '');
#define('AWS_DYNAMODB_URL', 'http://localhost:8000');


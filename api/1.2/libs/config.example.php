<?php

/*
This file should be copied to api/1.2/libs/config.php and edited
to suit the local environment.  config.php is gitignored, and can't
be committed.
*/

/* Postgres database config */

$PG_HOST = 'localhost';
$PG_PORT = 5432;
$PG_USER = 'root';
$PG_PASS = '';
$PG_DB = 'bowdlerize';

/* AMQP Config */

$AMQP_HOST = 'localhost';
$AMQP_USER = 'guest';
$AMQP_PASS = 'guest';
$AMQP_VHOST= '/';

# $SUBMIT_ROUTING_KEY = 'check.org'; # submit via robots.txt checker
$SUBMIT_ROUTING_KEY = 'url.org'; # submit directly to ISP queues

define('SITE_EMAIL', 'blocked@example.com');
define('SITE_NAME', 'Example.com');
define('SITE_URL', 'https://www.example.com');
define('CONFIRM_URL', 'https://www.example.com/confirm');
define('VERIFY_URL', 'https://www.example.com/verify');

define('MAIL_DOMAIN', 'cases.example.com');

define('FEATURE_SEND_SUBSCRIBE_EMAIL', false); # set to true to enable mail sending

define('FEATURE_EMAIL_TRACKING', false); # use individual addresses for tracking queries

define('AMQP_PUBLIC_QUEUE_TIMEOUT', 4*86400*1000); # queued jobs expire after 4 days (in milliseconds);

define('REDIS', 'localhost:6379');

define('DEBUG_LOG', false);

define('CONFIG_LATEST', '2021041301');

$ELASTIC = "http://localhost:9200";

define('ELASTIC_ADULT_FILTER', 'escort~ OR fetish OR stripper~ OR porn~ OR blowjob~ OR femdom~ OR fuck~');

# https://bbfc.co.uk/what-classification/mobile-content/appeals/mobile-complaint-form
define('BBFC_FORM_URL', 'https://bbfc.co.uk/what-classification/mobile-content/appeals/mobile-complaint-form');
define('BBFC_SUBMIT_URL', 'https://bbfc.co.uk/what-classification/mobile-content/appeals/mobile-complaint-form');

$REQUEUE_EXCLUDE_SOURCES = array();

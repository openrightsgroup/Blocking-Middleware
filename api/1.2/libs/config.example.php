<?php

/*
This file should be copied to api/1.2/libs/config.php and edited
to suit the local environment.  config.php is gitignored, and can't
be committed.
*/

/* MySQL database config */

$dbhost = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'bowdlerize';

/* AMQP Config */

$AMQP_HOST = 'localhost';
$AMQP_USER = 'guest';
$AMQP_PASS = 'guest';
$AMQP_VHOST= '/';

# $SUBMIT_ROUTING_KEY = 'check.org'; # submit via robots.txt checker
$SUBMIT_ROUTING_KEY = 'url.org'; # submit directly to ISP queues

/* GCM creds */

define("GOOGLE_API_KEY", "GOOGLE_KEY");

define('SITE_EMAIL', 'blocked@example.com');
define('SITE_NAME', 'Example.com');
define('SITE_URL', 'https://www.example.com');
define('CONFIRM_URL', 'https://www.example.com/confirm');

define('FEATURE_SEND_SUBSCRIBE_EMAIL', false); # set to true to enable mail sending

define('AMQP_PUBLIC_QUEUE_TIMEOUT', 4*86400*1000); # queued jobs expire after 4 days (in milliseconds);

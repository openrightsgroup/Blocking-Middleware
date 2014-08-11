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

$SUBMIT_ROUTING_KEY = 'check.org'; # submit via robots.txt checker
# $SUBMIT_ROUTING_KEY = 'url.org'; # submit directly to ISP queues

/* GCM creds */

define("GOOGLE_API_KEY", "GOOGLE_KEY");

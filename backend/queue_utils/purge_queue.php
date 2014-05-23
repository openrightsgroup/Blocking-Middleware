<?php

include_once __DIR__ . "/../../api/1.2/libs/amqp.php";

$ch = amqp_connect();


print "Purging: " . $argv[1] . "\n";
try {
$q = new AMQPQueue($ch);
$q->setName($argv[1]);
$q->purge();
} catch(Exception $e) {
	print "Error: " . $e->getMessage();
}

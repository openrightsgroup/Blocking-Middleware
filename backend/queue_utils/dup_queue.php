<?php

include_once __DIR__ . "/../api/1.2/libs/amqp.php";

$ch = amqp_connect();

$ex = new AMQPExchange($ch);

$q = new AMQPQueue($ch);
$q->setName($argv[1]);

$c = 0;
while ($msg = $q->get()) {
	$body = $msg->getBody();
	print $body . "\n";
	$ex->publish($body, $argv[2], AMQP_NOPARAM);
	$c += 1;
}
print "Copied $c messages to {$argv[2]}\n";

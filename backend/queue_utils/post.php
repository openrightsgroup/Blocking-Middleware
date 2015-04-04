<?php

include __DIR__ . '/../../api/1.2/libs/amqp.php';

$ch = amqp_connect();

$ex = new AMQPExchange($ch);
if ($argv[2] == 'url.public' || $argv[2] == 'check.org') {
	$ex->setName('org.blocked');
}

$message = json_encode(array(
	'url'=> $argv[1],
	'hash'=> md5($argv[1]),
));
$ex->publish($message,$argv[2]);


<?php

$dir = dirname(__FILE__);
include "$dir/../../api/1.2/libs/amqp.php";

$ch = amqp_connect();

$q = new AMQPQueue($ch);
$q->setName($argv[1]);
$q->setFlags(AMQP_PASSIVE);
$q->declare();

$count = 0;

function read($msg, $queue) {
	global $count, $argv;
	$data = (array)json_decode($msg->getBody()) ;
	print $data['url'] . "\n";
	$queue->ack($msg->getDeliveryTag());
	$count ++;
	if ($count > $argv[2]) {
		return false;
	}
}

$q->consume("read");


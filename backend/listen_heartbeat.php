<?php

# long running MySQL connections
ini_set('mysqli.reconnect',1);

include_once __DIR__ . "/../api/1.2/libs/DB.php";
include_once __DIR__ . "/../api/1.2/libs/amqp.php";

$ch = amqp_connect();
$q = new AMQPQueue($ch);
$q->setName('heartbeat');

$conn = db_connect();

function process_result($msg, $queue) {
	global $conn;

	$data = (array)json_decode($msg->getBody());
	$queue->ack($msg->getDeliveryTag());

	error_log("Got heartbeat: {$data['probe_uuid']} {$data['date']}");

	$conn->query("update probes set lastSeen = ? where uuid = ?", 
		array($data['date'], $data['probe_uuid'])
	);
}

$q->consume("process_result");

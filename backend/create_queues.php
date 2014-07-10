<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";
include "$dir/../api/1.2/libs/amqp.php";
$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$ch = amqp_connect();

$ex2 = new AMQPExchange($ch);
$ex2->setName('org.results');
$ex2->setType('topic');
$ex2->setFlags(AMQP_DURABLE);
$ex2->declare();

$ex = new AMQPExchange($ch);
$ex->setName('org.blocked');
$ex->setType('topic');
$ex->setFlags(AMQP_DURABLE);
$ex->declare();

function createqueue($ch, $name,  $key, $exchange = 'org.blocked') {
	$q = new AMQPQueue($ch);
	$q->setName($name);
	$q->setFlags(AMQP_DURABLE);
	$q->declare();
	$q->bind($exchange, $key);
}

$result = $conn->query("select name, queue_name from isps where queue_name is not null", array());
while ($isp = $result->fetch_assoc()) {
	if (strpos($isp['name'], ',') !== false) {
		continue;
	}
	print "Creating queue: " . $isp['queue_name'] . "\n";
	createqueue($ch, 'url.'.$isp['queue_name'].'.public',  'url.public');
	createqueue($ch, 'url.'.$isp['queue_name'].'.ooni',  'url.public');
	createqueue($ch, 'url.'.$isp['queue_name'].'.org',  'url.org');
}

createqueue($ch, "results",  "results.#");
createqueue($ch, "heartbeat",  "probe.heartbeat.#");


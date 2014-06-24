<?php

include_once "config.php";

function amqp_connect_full() {
	// returns an open AMQP connection and channel
	global $AMQP_HOST, $AMQP_USER, $AMQP_PASS, $AMQP_VHOST;

	$amqp = new AMQPConnection(array(
		'host'=>$AMQP_HOST,
		'login'=>$AMQP_USER,
		'password'=>$AMQP_PASS,
		'vhost' => $AMQP_VHOST,
	));
	$amqp->connect();
	return array($amqp, new AMQPChannel($amqp));
}

function amqp_connect() {
	// returns a channel for an open AMQP connection
	list($conn, $ch) = amqp_connect_full();
	return $ch;
}

function get_queue_name($ispname) {
	// there's probably a better place for this than here

	return strtolower(str_replace(" ","_",$ispname));
}
function create_queue($ch, $name,  $key, $exchange = 'org.blocked') {
	$q = new AMQPQueue($ch);
	$q->setName($name);
	$q->setFlags(AMQP_DURABLE);
	$q->declare();
	$q->bind($exchange, $key);
}

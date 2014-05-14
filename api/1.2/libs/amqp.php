<?php

$AMQP_HOST = 'localhost';
$AMQP_USER = 'guest';
$AMQP_PASS = 'guest';
$AMQP_VHOST= '/';

function amqp_connect() {
	// returns an open AMQP channel
	global $AMQP_HOST, $AMQP_USER, $AMQP_PASS, $AMQP_VHOST;

	$amqp = new AMQPConnection(array(
		'host'=>'localhost',
		'user'=>'guest', 
		'password'=>'guest',
		'vhost' => $AMQP_VHOST,
	));
	$amqp->connect();
	return new AMQPChannel($amqp);
}

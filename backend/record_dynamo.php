<?php

include_once __DIR__ . "/silex/vendor/autoload.php";

include_once __DIR__ . "/../api/1.2/libs/DB.php";
include_once __DIR__ . "/../api/1.2/libs/amqp.php";
include_once __DIR__ . "/../api/1.2/libs/pki.php";
include_once __DIR__ . "/../api/1.2/libs/exceptions.php";
include_once __DIR__ . "/../api/1.2/libs/services.php";

$opts = getopt('v', array('exchange:','queue:','debug','setup'));


function opt($name, $default=null) {
    global $opts;

    return (isset($opts[$name])) ? $opts[$name] : $default;
}

function flag($flag) {
    global $opts;

    return isset($opts[$flag]);
}


$ch = amqp_connect();

$queue_name = opt('queue', "result_payload");

$q = new AMQPQueue($ch);
$q->setName($queue_name);
$q->setFlags(AMQP_DURABLE);
$q->declare();

$q->bind(opt('exchange', 'org.blocked'), $queue_name . '.#');

$dynamo = new DynamoWrapper(
    AWS_DYNAMODB_ACCESS_KEY,
    AWS_DYNAMODB_SECRET_KEY,
    AWS_DYNAMODB_URL
);


function receive_message($msg, $queue) {
    global $dynamo;

    $queue->ack($msg->getDeliveryTag());
    $data = (array)json_decode($msg->getBody());
    error_log("Got result: {$data['probe_uuid']} {$data['url']} {$data['date']} {$data['status']} " . count($data['request_data']) );

    try {
        $reqdata = array(
            'url' => $data['url'],
            'created' => $data['date'],
            'id' => $data['test_uuid'],
            'requests' => $data['request_data']
        );
        $dynamo->store($reqdata);
    } catch (Exception $e) {
        error_log("dynamo->store failed.");
        error_log("Caught exception: " . get_class($e));
        error_log("Message was: " . $e->getMessage());
    }

    return true;
}

if (flag('setup')) {
    $dynamo->createTable();
}

$q->consume("receive_message");
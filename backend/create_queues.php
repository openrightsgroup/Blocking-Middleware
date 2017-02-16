<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";
include "$dir/../api/1.2/libs/amqp.php";
$conn = db_connect();

list($amqp, $ch) = amqp_connect_full();

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

function delete_queue($name) {
    global $amqp;
    try {
        $c = new AMQPChannel($amqp);
        $q = new AMQPQueue($c);
        $q->setName($name);
        $q->delete();
    } catch (AMQPQueueException $e) {
        if (strpos($e->getMessage(), "NOT_FOUND") === false) {
            throw $e;
        }
    }

}

function createqueue($name,  $key, $ttl=0, $recreate=false) {
    global $amqp;
    $exchange = 'org.blocked';

    try {
        $c = new AMQPChannel($amqp);
        $q = new AMQPQueue($c);
        $q->setName($name);
        $q->setFlags(AMQP_DURABLE);
        if ($ttl) {
            $q->setArgument("x-message-ttl", $ttl);
        }
        $q->declare();
        $q->bind($exchange, $key);
    } catch (AMQPQueueException $e) {
        if (strpos($e->getMessage(), "inequivalent arg") !== false && $recreate) {
            try {
                print "Recreating $name\n";
                delete_queue($name);
                $c = new AMQPChannel($amqp);
                $q = new AMQPQueue($c);
                $q->setName($name);
                $q->setFlags(AMQP_DURABLE);
                if ($ttl) {
                    $q->setArgument("x-message-ttl", $ttl);
                }
                $q->declare();
                $q->bind($exchange, $key);
            } catch (AMQPQueueException $e2) {
                echo "Recreate Queue error: " .  $e2->getMessage() . "\n";
            }
        } else {
            echo "Queue error: " .  $e->getMessage() . "\n";
        }
    }
}

$result = $conn->query("select name, queue_name, isp_type from isps where queue_name is not null", array());
while ($isp = $result->fetch()) {
	if (strpos($isp['name'], ',') !== false) {
		continue;
	}
	print "Creating queue: " . $isp['queue_name'] . "\n";
	createqueue('url.'.$isp['queue_name'].'.public',  'url.public', AMQP_PUBLIC_QUEUE_TIMEOUT, true);
	createqueue('url.'.$isp['queue_name'].'.org',  'url.org');

	#createqueue('url.'.$isp['queue_name'].'.ooni',  'url.public', AMQP_PUBLIC_QUEUE_TIMEOUT);
	delete_queue('url.'.$isp['queue_name'].'.ooni');

    if ($isp['isp_type'] == 'fixed') {
        createqueue('url.'.$isp['queue_name'].'.fixed', 'url.fixed', AMQP_PUBLIC_QUEUE_TIMEOUT, true);
    }

    #createqueue('admin.view.' . $isp['queue_name'], 'admin.view.#');
}

createqueue("results",  "results.#");
createqueue("check",  "check.#");
createqueue("heartbeat",  "probe.heartbeat.#");


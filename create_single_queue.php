<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";
include "$dir/../api/1.2/libs/amqp.php";
$conn = db_connect();

list($amqp, $ch) = amqp_connect_full();


function createqueue($name,  $key='', $ttl=0, $recreate=false) {
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
        if ($key) {
            $q->bind($exchange, $key);
        }
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
                if ($key) {
                    $q->bind($exchange, $key);
                }
            } catch (AMQPQueueException $e2) {
                echo "Recreate Queue error: " .  $e2->getMessage() . "\n";
            }
        } else {
            echo "Queue error: " .  $e->getMessage() . "\n";
        }
    }
}

if (strpos($argv[2], ".public") !== false) {
    $timeout = AMQP_PUBLIC_QUEUE_TIMEOUT;
} else {
    $timeout = 0;
}

create_queue($argv[1], $argv[2], $timeout);

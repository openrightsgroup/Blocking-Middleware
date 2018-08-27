<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";
include "$dir/../api/1.2/libs/amqp.php";

list($amqp, $ch) = amqp_connect_full();


function createqueue($name,  $key='', $ttl=0, $recreate=true) {
    global $amqp, $ch;
    $exchange = 'org.blocked';

    try {
        $q = new AMQPQueue($ch);
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
                $q = new AMQPQueue($ch);
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

if (strpos($argv[1], ".public") !== false) {
    $timeout = AMQP_PUBLIC_QUEUE_TIMEOUT;
} else {
    $timeout = 0;
}

createqueue($argv[1], $argv[2], $timeout);

<?php

class QueueManager {

    var $exchange_name = "org.blocked";

    public function __construct($conn, $amqp, $ch) {
        $this->conn = $conn;
        $this->amqp = $amqp;
        $this->ch = $ch;
    }

    function setup_exchange($name) {
        $ex = new AMQPExchange($this->ch);
        $ex->setName($name);
        $ex->setType('topic');
        $ex->setFlags(AMQP_DURABLE);
        $ex->declare();

        return $ex;
    }

    public function setup_exchanges() {
        $this->setup_exchange('org.results');
        $this->exchange = $this->setup_exchange($this->exchange_name);
    }

    function delete_queue($name) {
        try {
            $c = new AMQPChannel($this->amqp);
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
        $exchange = $this->exchange_name;

        try {
            $c = new AMQPChannel($this->amqp);
            $q = new AMQPQueue($c);
            $q->setName($name);
            $q->setFlags(AMQP_DURABLE);
            if ($ttl) {
                $q->setArgument("x-message-ttl", $ttl);
            }
            $q->declare();
            $q->bind($this->exchange_name, $key);
        } catch (AMQPQueueException $e) {
            if (strpos($e->getMessage(), "inequivalent arg") !== false && $recreate) {
                try {
                    print "Recreating $name\n";
                    $this->delete_queue($name);
                    $c = new AMQPChannel($this->amqp);
                    $q = new AMQPQueue($c);
                    $q->setName($name);
                    $q->setFlags(AMQP_DURABLE);
                    if ($ttl) {
                        $q->setArgument("x-message-ttl", $ttl);
                    }
                    $q->declare();
                    $q->bind($this->exchange, $key);
                } catch (AMQPQueueException $e2) {
                    echo "Recreate Queue error: " .  $e2->getMessage() . "\n";
                }
            } else {
                echo "Queue error: " .  $e->getMessage() . "\n";
            }
        }
    }

    public function setup_isp_queues() {
        $result = $this->conn->query("select name, queue_name, isp_type, regions from isps where queue_name is not null", array());
        while ($isp = $result->fetch()) {
        	if (strpos($isp['name'], ',') !== false) {
        		continue;
        	}
        	print "Creating queue: " . $isp['queue_name'] . "\n";
        	$this->createqueue('url.'.$isp['queue_name'].'.public',  'url.public', AMQP_PUBLIC_QUEUE_TIMEOUT, true);
        	$this->createqueue('url.'.$isp['queue_name'].'.org',  'url.org');

            if (strpos($isp['regions'], 'eu') !== false) {
                $this->createqueue('url.' . $isp['queue_name'] . '.public', 'url.public.eu', AMQP_PUBLIC_QUEUE_TIMEOUT, true);
            }
            if (strpos($isp['regions'], 'gb') !== false) {
                $this->createqueue('url.' . $isp['queue_name'] . '.public', 'url.public.gb', AMQP_PUBLIC_QUEUE_TIMEOUT, true);
            }

        	$this->delete_queue('url.'.$isp['queue_name'].'.ooni');

            if ($isp['isp_type'] == 'fixed') {
                $this->createqueue('url.'.$isp['queue_name'].'.fixed', 'url.fixed', AMQP_PUBLIC_QUEUE_TIMEOUT, true);
            }

            #createqueue('admin.view.' . $isp['queue_name'], 'admin.view.#');
        }
    }

    public function setup_misc_queues() {
        $this->createqueue("selftest", "selftest");
        $this->createqueue("results",  "results.#");
        $this->createqueue("check",  "check.#");
        $this->createqueue("heartbeat",  "probe.heartbeat.#");

    }

    public function setup_queues() {
        $this->setup_isp_queues();
        $this->setup_misc_queues();
    }

    public function setup() {
        $this->setup_exchanges();
        $this->setup_queues();
    }

}

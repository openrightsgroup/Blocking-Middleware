<?php

include "../1.2/libs/amqp.php";
$ch = amqp_connect_full();

$network = $_POST['network'];

$q = new AMQPQueue($ch);
$q->setName("view.$network");
$q->declare();
$st = time();

while (time() - $st < 10) {
    $msg = $q->get();
    if ($msg !== false) {
        break;
    }
    sleep(0.5);
}

if ($msg === false) {
    print "Timeout.";
    exit();
}


$q->ack($msg->getDeliveryTag());
$data = (array)json_decode($msg->getBody());

print $data['content'];




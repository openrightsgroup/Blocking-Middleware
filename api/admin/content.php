<?php

include "../1.2/libs/amqp.php";
$ch = amqp_connect();

$network = $_GET['network'];
$hash = $_GET['hash'];

$q = new AMQPQueue($ch);
$q->setName("view.$network.$hash");
$q->setFlags(AMQP_AUTODELETE);
$q->declare();
$q->bind("org.blocked", "admin.results.$network.$hash");
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
    $q->delete();
    exit();
}


$q->ack($msg->getDeliveryTag());
$q->delete();
$data = (array)json_decode($msg->getBody());

print $data['content'];




<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";
include "$dir/../api/1.2/libs/amqp.php";
include "$dir/../api/1.2/libs/queue.php";
$conn = db_connect();

list($amqp, $ch) = amqp_connect_full();

$qmgr = new QueueManager($conn, $amqp, $ch);

$qmgr->setup();




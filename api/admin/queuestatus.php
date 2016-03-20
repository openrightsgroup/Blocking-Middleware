<?php

include "template.inc.php";

/* Reads queue status from RabbitMQ, and displays stats in nice table.

Requires a sudoers.d file like:

Defaults:apache         !requiretty
apache  ALL=(ALL:ALL)   NOPASSWD: /usr/sbin/rabbitmqctl -q list_queues name messages messages_unacknowledged consumers

*/

$fp = popen("/usr/bin/sudo /usr/sbin/rabbitmqctl -q list_queues name messages messages_unacknowledged consumers", "r");
if (!$fp) {
    die ("Unable to get queue status");
}

$out = array();
while ($line = fgets($fp)) {
    $parts = explode("\t", $line);
    if (substr($parts[0],0,10) == "admin.view") {
        continue;
    }
    $out[] = array(
        'name' => $parts[0],
        'messages' => (int)$parts[1],
        'in_progress' => $parts[2],
        'workers' => (int)$parts[3]
        );
}
fclose($fp);


$twig->display("queuestatus.html", array(
    'queues' => $out
    ));

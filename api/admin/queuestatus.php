<?php

include "template.inc.php";

$fp = popen("/usr/bin/sudo /usr/sbin/rabbitmqctl -q list_queues name messages messages_unacknowledged consumers", "r");
if (!$fp) {
    die ("Unable to get queue status");
}

$out = array();
while ($line = fgets($fp)) {
    $parts = explode("\t", $line);
    $out[] = array(
        'name' => $parts[0],
        'messages' => $parts[1],
        'in_progress' => $parts[2],
        'workers' => $parts[3]
        );
}
fclose($fp);


$twig->display("queuestatus.html", array(
    'queues' => $out
    ));

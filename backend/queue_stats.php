<?php

include __DIR__ . "/../api/1.2/libs/DB.php";

$conn = db_connect();

if (($fp = popen("/usr/sbin/rabbitmqctl list_queues","r")) == NULL) {
	fwrite(stderr, "Unable to get Queue list\n");
	exit(1);
}

$dt = date('Y-m-d H:i:s');
while ($line = fgets($fp)) {
	$results = array();
	if (preg_match("/(url\.(.*)\.(ooni|org|public))\t(\d+)/", $line, &$matches)) {
		$isp = $matches[2];
		$type = $matches[3];
		$count = $matches[4];

		$conn->query("insert into queue_length(created, isp, type, length) values (?,?,?,?)",
		array($dt, $isp, $type, $count));
	}
}
$conn->commit();


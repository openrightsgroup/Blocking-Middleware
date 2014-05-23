<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";
include "$dir/../api/1.2/libs/amqp.php";
$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

define('MAXQ', 750);
define('MINQ', 250);

$ch = amqp_connect();

if (($fp = popen("/usr/sbin/rabbitmqctl list_queues","r")) == NULL) {
	fwrite(stderr, "Unable to get Queue list\n");
	exit(1);
}

# manage subscription to topics based on queue depth

$unsub = 0;
$queues = 0;

while ($line = fgets($fp)) {
	$results = array();
	if (preg_match("/(url\..*\.public)\t(\d+)/", $line, &$matches)) {
		print "Checking: " . $matches[1] . ": ". $matches[2] . "\n";
		$queues ++;
		if ($matches[2] < MINQ) {
			print "Subscribing...\n";
			$q = new AMQPQueue($ch);
			$q->setName($matches[1]);
			$q->bind("org.blocked","url.public");
		}
		if ($matches[2] > MAXQ) {
			print "UnSubscribing...\n";
			$unsub++;
			$q = new AMQPQueue($ch);
			$q->setName($matches[1]);
			try {
				$q->unbind("org.blocked","url.public");
			} catch (AMQPQueueException $e ) {
				$ch = amqp_connect();
			}
		}
	}
}
			
if ($unsub == $queues) {
	print "No queues receiving messages; exiting\n";
	exit(0);
}

$ex = new AMQPExchange($ch);
$ex->setName('org.blocked');
$ex->setType('topic');
$ex->setFlags(AMQP_PASSIVE);
$ex->declare();
		
$result = $conn->query("select urlid, url, hash from urls 
	where (lastpolled is null or lastpolled < date_sub(now(), interval 7 day)) and 
	source in ('user','alexa') order by lastpolled limit 500", array());

$c = 0;
print "Sending URLs ...\n";
while ($row = $result->fetch_row()) {
	$msg = array('url' => $row[1], 'hash' => $row[2]);
	$ex->publish(json_encode($msg), "url.public", AMQP_NOPARAM);
	$conn->query("update urls set lastpolled = now() where urlid = ?", array($row[0]));
	$c += 1;
}

print "$c urls sent.\n";

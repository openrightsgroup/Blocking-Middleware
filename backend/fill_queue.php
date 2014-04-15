<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";

/*
This script will backfill the queue table with all submitted URLs.
Run from cron to feed the queue when:
 * New URLs have been added
 * New ISPs have been added
*/

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$result = $conn->query("select id, name from isps", array());
while ($isp = $result->fetch_assoc()) {
	$conn->query("insert ignore into queue (urlID, ispID) select urlID, ? from urls",
		array($isp['id'])
		);
	print "Added {$conn->affected_rows} rows for isp: {$isp['name']}\n";
}

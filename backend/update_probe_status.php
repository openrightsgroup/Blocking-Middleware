<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";
include "$dir/../api/1.2/libs/services.php";

$conn = db_connect();

if (count($argv) != 3) {
    print "Usage: {$argv[0]} <probe_uuid> <status>\n";
    print "\tStatus: 0 (OK), 3 (Critical)\n";
    exit(1);
}


$sql = "update probes set selftest_status = ?, selftest_updated = now() where uuid = ?"; 
$conn->query($sql, array($argv[2], $argv[1]));



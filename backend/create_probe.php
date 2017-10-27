<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";
include "$dir/../api/1.2/libs/services.php";

$conn = db_connect();


if (count($argv) != 5) {
    print "Usage: {$argv[0]} <probe_uuid> <secret> <type> <user_email>\n";
    exit(1);
}

$res = $conn->query("select id from users where email = ?", array($argv[4]));
$row = $res->fetch();
if (!$row) {
    print "User not found: {$argv[4]}\n";
    exit(2);
}

$sql = "insert into probes(uuid, secret, type, userid) values (?,?,?,?)";
$conn->query($sql, array($argv[1], $argv[2], $argv[3], $row[0]));



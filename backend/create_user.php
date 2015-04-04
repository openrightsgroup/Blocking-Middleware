<?php

include "../api/1.2/libs/DB.php";
include "../api/1.2/libs/services.php";

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$sql = "insert into users(email, secret) values (?,?)";

if (count($argv) != 3) {
    print "Usage: {$argv[0]} <username> <secret>\n";
    exit(1);
}

$conn->query($sql, array($argv[1], $argv[2]));
$conn->commit();



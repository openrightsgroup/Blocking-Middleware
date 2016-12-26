#!/usr/bin/php
<?php

$dir = dirname(__FILE__);
include_once "$dir/../api/1.2/libs/DB.php";

$conn = db_connect();

$q = $conn->query("UPDATE users set administrator=1 where email = ?",
	array($argv[1]));

if ($q->rowCount() > 0) {
	print "$argv[1] has been granted administrator privileges.\n";
} else {
	print "$argv[1]: user not found.\n";
}

?>

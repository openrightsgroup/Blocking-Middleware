<?php

include_once "../api/1.2/libs/config.php";
include_once "../api/1.2/libs/DB.php";
include_once "../api/1.2/libs/pki.php";
include_once "../api/1.2/libs/services.php";

if (!$argv[1] || !$argv[2]) {
	print "Required parameters: probe uuid, network_name";
	exit(1);
}

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);
$loader = new ProbeLoader($conn);

$probe = $loader->load($argv[1]);

print Middleware::createSignatureHash(
	implode(":", array($argv[1], $argv[2])),
	$probe['secret']
	) . "\n";


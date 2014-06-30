<?php

include_once __DIR__ . "/../api/1.2/libs/DB.php";
$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$result = $conn->query("
select count(*) from urls 
	", array());

$row = $result->fetch_row();

$stats = array(
	'urls_reported' => $row[0],
	);

$result = $conn->query("select count(distinct urlid) from results",array());
$row = $result->fetch_row();
$stats['urls_tested'] = $row[0];

$result = $conn->query("select count(distinct urlid) from results where status = 'blocked'", array());
$row = $result->fetch_row();
$stats['blocked_sites_detected'] = $row[0];

print_r($stats);

foreach($stats as $name => $value) {
	$conn->query(
		"replace into stats_cache (name, value) values (?,?)",
		array($name, $value)
		);
}

$conn->commit();

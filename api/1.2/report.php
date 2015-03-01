<?php

include_once "libs/DB.php";

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$body = $HTTP_RAW_POST_DATA;
} elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
	$fp = fopen('php://input','r');
	$body = '';
	while ($line = fread($fp, 1024)) {
		$body .= $line;
	}
	fclose($fp);
}

if (substr(@$_SERVER['PATH_INFO'], -5, 5) == "close") {
	error_log("Closing report");
	exit();
}

$data = json_decode($body);

if (!isset($data->report_id)) {
	$conn->query("insert into reports(data, created) values (?, now())",
		array($body));
	$id = $conn->insert_id;
	print json_encode(array(
		'report_id' => "$id",
		'backend_version' => 0.01,
	));
	exit();
} else {
	# write to log file
	$conn->query("insert into report_entries(report_id, data, created) values (?,?,now())",
	array($data->report_id, $data->content));
}

	




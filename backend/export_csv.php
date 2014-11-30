<?php


include_once __DIR__ . "/../api/1.2/libs/DB.php";
$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$conn->real_query(
"select url, urls.inserted url_submitted, network_name, filter_level, status, results.created, http_status, config, results.category, results.blocktype from results inner join urls using (urlid)");
$result = $conn->use_result();

if (count($argv) == 1) {
	$filename = null;
	$fp = fopen('php://stdout','w');
} else {
	$filename = $argv[1];
	$fp = fopen($filename,'w');
}

fputcsv($fp, array('URL','URL Submission Timestamp','Network Name','Filter Level','Status','Result Timestamp','HTTP Status','Probe Config','Block Category','Block Type'));
while ($row = $result->fetch_row()) {
	#print implode($row, "\t") . "\n";
	fputcsv($fp, $row);
}
$result->free_result();
fclose($fp);


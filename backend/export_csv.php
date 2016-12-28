<?php


include_once __DIR__ . "/../api/1.2/libs/DB.php";
$conn = db_connect();

$result = $conn->query(
"select url, urls.inserted url_submitted, network_name, filter_level, results.status, results.created, http_status, config, results.category, results.blocktype from results inner join urls using (urlid)");

if (count($argv) == 1) {
	$filename = null;
	$fp = fopen('php://stdout','w');
} else {
	$filename = $argv[1];
	$fp = fopen($filename,'w');
}

fputcsv($fp, array('URL','URL Submission Timestamp','Network Name','Filter Level','Status','Result Timestamp','HTTP Status','Probe Config','Block Category','Block Type'));
while ($row = $result->fetch(PDO::FETCH_NUM)) {
	#print implode($row, "\t") . "\n";
	fputcsv($fp, $row);
}
fclose($fp);


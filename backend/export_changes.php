#!/usr/bin/php
<?php

include_once __DIR__ . "/../api/1.2/libs/DB.php";
$conn = db_connect();



if (@$argv[1] == '--all') {
	# export entire change history, from earliest meaningful date
	$start_date = '2014-07-07';
} else {
	# export starting from yesterday
	# doing the date calculation in the DB, since PHP dates are a pain
	$start_date = date('Y-m-d');
}
# end export at midnight today
$end_date = date('Y-m-d');

$result = $conn->query("select url, network_name, created, old_status, new_status
from url_status_changes stat inner join urls on urls.urlid = url_status_changes.urlid
where stat.created >= ?  and stat.created < (? + interval '1 days')
order by created", array($start_date, $end_date));

$fp = fopen('php://stdout','w');
fputcsv($fp, array("URL","Network","Date Changed","Old Status","New Status"));
while ($row = $result->fetch(PDO::FETCH_NUM)) {
	fputcsv($fp, $row);
}


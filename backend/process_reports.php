<?php


include_once __DIR__ . "/../api/1.2/libs/DB.php";
include_once __DIR__ . "/../api/1.2/libs/exceptions.php";
include_once __DIR__ . "/../api/1.2/libs/services.php";
include_once __DIR__ . "/../api/1.2/libs/config.php";

include_once __DIR__ . "/silex/vendor/autoload.php";

// remove sites from the search index when they are flagged

$conn = db_connect();
$elastic = new ElasticService($ELASTIC);
print "$ELASTIC\n";

$q = $conn->query("select isp_reports.id, isp_reports.urlid, report_type from 
    isp_reports
    where isp_reports.report_type <> 'unblock' 
    and isp_reports.created >= (now() - interval '2 hour')",
    array()
    );

foreach($q as $row) {
    echo "Report {$row['id']}; Removing search index: {$row['urlid']}: {$row['report_type']}\n";
    $conn->query("delete from blocked_dmoz where urlid = ?",
        array($row['urlid'])
        );
    $elastic->delete($row['urlid']);
}



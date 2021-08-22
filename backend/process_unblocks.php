<?php


include_once __DIR__ . "/../api/1.2/libs/DB.php";
include_once __DIR__ . "/../api/1.2/libs/amqp.php";
include_once __DIR__ . "/../api/1.2/libs/pki.php";
include_once __DIR__ . "/../api/1.2/libs/exceptions.php";
include_once __DIR__ . "/../api/1.2/libs/services.php";

include_once __DIR__ . "/silex/vendor/autoload.php";

/*****************
 * Inspect the previous day's results, and update the "unblocked" column of any
 * ISP_reports for that url/network
 *
 *****************/

$conn = db_connect();
$elastic = new ElasticService($ELASTIC);

$q = $conn->query("select isp_reports.id, usc.created, isp_reports.urlid from 
    isp_reports
    inner join url_status_changes usc on isp_reports.urlid = usc.urlid and isp_reports.network_name = usc.network_name
    where usc.new_status = 'ok' and isp_reports.unblocked = 0 and usc.created >= isp_reports.created 
    and usc.created >= (now() - interval '1 day')",
    array()
    );

foreach($q as $row) {
    echo "Updating report: " . $row['id'] . "\n";
    $conn->query("update isp_reports set unblocked = 1, last_updated = ? where id = ?",
        array($row['created'], $row['id'])
        );
    $elastic->delete($row['urlid']);
}

/*****************
 * update unblocked status on BBFC reports
 *
 *****************/

$q = $conn->query("
    select isp_reports.id, isp_reports.urlid, count(*), sum(r2.unblocked)
    from isp_reports
    inner join isp_reports r2 on r2.urlid = isp_reports.urlid
    inner join isps on isps.name = r2.network_name
    where isp_reports.network_name = 'BBFC'
        and isp_reports.unblocked = 0
        and isps.isp_type = 'mobile'
    group by isp_reports.id, isp_reports.urlid
    ");

# when all mobile ISPs are unblocked==1, set the BBFC report unblocked=1

foreach($q as $row) {
    echo "Updating BBFC report: " . $row['id'] . "\n";
    if ($row['count'] == $row['sum']) {
        $conn->query("update isp_reports set unblocked = 1, last_updated=now() where id = ?", array($row['id']));
    }
}



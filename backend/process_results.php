<?php


include_once __DIR__ . "/../api/1.2/libs/DB.php";
include_once __DIR__ . "/../api/1.2/libs/amqp.php";
include_once __DIR__ . "/../api/1.2/libs/pki.php";
include_once __DIR__ . "/../api/1.2/libs/exceptions.php";
include_once __DIR__ . "/../api/1.2/libs/services.php";
include_once __DIR__ . "/../api/1.2/libs/email.php";

include_once __DIR__ . "/silex/vendor/autoload.php";

/* Look for blocked results that have pending reports, and send them */

$conn = db_connect();

$isploader = new IspLoader($conn);
$urlloader = new UrlLoader($conn);
$reportloader = new ISPReportLoader($conn);

$loader = new Twig_Loader_Filesystem(__DIR__ . "/../api/1.2/templates");
$templateloader = new Twig_Environment($loader, array(
    'cache' => false,
    'debug' => true
));

$q = $conn->query("select isp_reports.id as report_id, results.status, results.created, results.network_name
    from isp_reports
    inner join contacts on contact_id = contacts.id
    inner join results on isp_reports.urlid = results.urlid and isp_reports.network_name = results.network_name
    where isp_reports.status = 'pending' 
        and results.created >= (now() - interval '1 day')
        and contacts.verified = 1",
    array()
    );

foreach($q as $result) {
    echo "Updating report: " . $result['report_id'] . "\n";

    $row = $reportloader->load($result['report_id']);

    if ($row['status'] != 'pending') {
        # we might have already seen this result, if there have been multiple results for a url/network since the
        # last reporting run
        error_log("Report {$row['report_id']} already processed.");
        continue;
    }

    if ($result['status'] == 'blocked') {
        $network = $isploader->load($row['network_name']);
        $url = $urlloader->loadByID($row['urlid']);

        $res = sendISPReport(
            $row['name'],
            $row['email'],
            $network, 
            $url['url'],
            $row['message'],
            $row['report_type'],
            $templateloader
        );
        if (!$res) {
            // email send error - continue to the next one without updating status
            continue;
        }

        $reportloader->set_status($result['report_id'], 'sent', $result['created']);
    } elseif ($result['status'] == 'ok') {
        $reportloader->set_status($result['report_id'], 'cancelled', $result['created']);
    } else {
        error_log("Report {$row['urlid']} left at pending; result was {$result['status']}\n");
    }
}



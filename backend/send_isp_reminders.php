<?php /** @noinspection DuplicatedCode */


include_once __DIR__ . "/../api/1.2/libs/DB.php";
include_once __DIR__ . "/../api/1.2/libs/exceptions.php";
include_once __DIR__ . "/../api/1.2/libs/services.php";
include_once __DIR__ . "/../api/1.2/libs/email.php";

include_once __DIR__ . "/silex/vendor/autoload.php";

// Send user verification reminders at 1 day, 1 week interval.

$conn = db_connect();
$contactloader = new ContactLoader($conn);

$loader = new Twig_Loader_Filesystem(__DIR__ . "/../api/1.2/templates");
$renderer = new Twig_Environment($loader, array(
    'cache' => false,
    'debug' => true
));

$opts = getopt("nh");

if (isset($opts['h'])) {
    print "Usage: {$argv[0]} [-n] [-h]\n\n";
    print "    -n    dummy mode\n";
    print "    -h    show help\n\n";
    exit(0);
}

/* send ISP reminder reminder 1 after 1 day */

$q = $conn->query("select isp_reports.*, admin_name, admin_email
    from isp_reports
    inner join isps on network_name = isps.name
    where status = 'SENT' and created < now() - interval ('14 days')
    and (last_reminder is null or last_reminder < now() - interval('30 days'))",array());
    
foreach ($q as $row) {    
    print "Sending reminder for {$row['network_name']} : {$row['admin_email']} {$row['urlid']}\n";

    if (isset($opts['n'])) {
        continue;
    }

    sendIspReminder($row, $renderer);

    $q2 = $conn->query("update isp_reports set last_reminder = now() where id = ?", $row['id']);

}


    

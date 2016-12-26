<?php


include_once __DIR__ . "/../api/1.2/libs/DB.php";
include_once __DIR__ . "/../api/1.2/libs/amqp.php";
include_once __DIR__ . "/../api/1.2/libs/pki.php";
include_once __DIR__ . "/../api/1.2/libs/exceptions.php";
include_once __DIR__ . "/../api/1.2/libs/services.php";

include_once __DIR__ . "/silex/vendor/autoload.php";

$ch = amqp_connect();

$ex = new AMQPExchange($ch);
$ex->setName('org.results');

$q = new AMQPQueue($ch);
$q->setName('unblock_watcher');

$q->bind("org.results", "*");

$loader = new Twig_Loader_Filesystem(__DIR__ . "/templates");
$twig = new Twig_Environment($loader, array(
    'cache' => false,
    'debug' => true
));

$conn = db_connect();
$urlloader = new UrlLoader($conn);
$isploader = new ISPLoader($conn);

function process_result($msg, $queue) {
	global $urlloader, $isploader, $conn;

	
	$data = (array)json_decode($msg->getBody());
	$queue->ack($msg->getDeliveryTag());

    if ($data['status'] != "ok") {
        // only interested in unblocked sites for now
        return true;
    }

    $url = $urlloader->load($data['url']);
    $isp = $isploader->load($data['network_name']);

    $res =  $conn->query("select isp_reports.*
    from isp_reports where urlID = ? and unblocked=0
    and network_name = ?", 
    array($url['urlID'], $isp['name'])
    );
    $report = $res->fetch();

    if (!$report) {
        print "No report found for {$url[urlID]}\n";
        return true;
    }

    // placeholder text

    $conn->query("UPDATE isp_reports set unblocked=1, notified=now(), last_updated=now()
        where id = ?",
        array($report['id'])
        );

    # send unblock notification email

    if ($report['send_updates']) {
        $msg = new PHPMailer();
        $msg->setFrom('blocked@openrightsgroup.org', 'Blocked Admin');
        $msg->addAddress($report['email'], $report['name']);
        $msg->Subject = "Site unblocked: " . $url['URL'] . " on " . $report['network_name'];
        $msg->isHTML(false);
        $msg->CharSet = 'utf-8';
        $msg->Body = $twig->render(
            'unblock_email.txt',
            array(
                'report' => $report,
                'url' => $url
                )
            );
    }

	return true;

}

$q->consume("process_result");

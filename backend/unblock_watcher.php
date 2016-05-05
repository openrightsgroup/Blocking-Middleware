<?php

# long running MySQL connections
ini_set('mysqli.reconnect',1);

include_once __DIR__ . "/../api/1.2/libs/DB.php";
include_once __DIR__ . "/../api/1.2/libs/amqp.php";
include_once __DIR__ . "/../api/1.2/libs/pki.php";
include_once __DIR__ . "/../api/1.2/libs/exceptions.php";
include_once __DIR__ . "/../api/1.2/libs/services.php";

$ch = amqp_connect();

$ex = new AMQPExchange($ch);
$ex->setName('org.results');

$q = new AMQPQueue($ch);
$q->setName('unblock_watcher');

$q->bind("org.results", "*");

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);
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
    $report = $res->fetch_assoc();

    if (!$report) {
        print "No report found for {$url[urlID]}\n";
        return true;
    }

    // placeholder text
    $msgemail = "Dear {$report[name]}, 

On {$report[created]} you reported the overblocking of {$url[url]} to 
{$report[network_name]}.  Blocked.org.uk has now detected that the site
has been unblocked.

Thank you for contributing to blocked.org.uk!";

    mail("{$report[name] <{$report[email]}>, ORG <blocked@openrightsgroup.org>",
        "Site unblocked: {$url[url]} on {$report[network_name]}",
        $msgemail,
        array( "From" => "ORG Admin <blocked@openrightsgroup.org>" )
        );

    $conn->query("UPDATE isp_reports set unblocked=1, notified=now(), last_updated=now()
        where id = ?",
        array($report['id'])
        );


	return true;

}

$q->consume("process_result");

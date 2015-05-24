<?php

# long running MySQL connections
ini_set('mysqli.reconnect',1);

include_once __DIR__ . "/../api/1.2/libs/DB.php";
include_once __DIR__ . "/../api/1.2/libs/amqp.php";
include_once __DIR__ . "/../api/1.2/libs/url.php";
include_once __DIR__ . "/../api/1.2/libs/pki.php";
include_once __DIR__ . "/../api/1.2/libs/exceptions.php";
include_once __DIR__ . "/../api/1.2/libs/services.php";

include_once "ooni_test.php";

$ch = amqp_connect();

$ex = new AMQPExchange($ch);
$ex->setName('org.blocked');

$q = new AMQPQueue($ch);
$q->setName('ooniresults');

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$iplookup = new IPLookupService($conn);
$isploader = new IspLoader($conn);

	
function load_result($id) {
	global $conn, $iplookup, $isploader, $ex;

	$conn->ping(); ## wake up DB

	$res = $conn->query("select * from report_entries where id = ?",
		array($id)
		);
	$entry = $res->fetch_assoc();
	if ($entry['processed']) {
		return;
	}
	$entry_data = yaml_parse($entry['data']);

	$res2 = $conn->query("select * from reports where id = ?",
		array($entry['report_id'])
		);
	$report = $res2->fetch_assoc();
	$report_data = json_decode($report['data']);
	$report_data2 = yaml_parse($report_data->content);


	$network_name = $iplookup->lookup($report_data2['probe_ip']);
	$network = $isploader->load($network_name);
	$network_key = str_replace(' ','_', strtolower($network['name']));

    $dirty = find_dirty($entry_data);

	$status = test_result($entry_data);

	# re-encode IDN urls as utf8 for the results processor.

	$msg = array(
		'network_name' =>  $network['name'],
		'ip_network' =>  $report_data2['probe_ip'],
		'url' =>  url_utf8($dirty['request']['url']),
		'http_status' =>  $dirty['response']['code'],
		'status' =>  $status,
		'probe_uuid' =>  $report_data->probe_uuid,
		'config' =>  -1,
		'category' =>  '',
		'blocktype' =>  '',
        'report_entry_id' => $id,
		'date' => strftime('%Y-%m-%d %H:%M:%S', $report_data2['start_time'])
		);
	print_r($msg);

	$ex->publish(
		json_encode($msg), 
		'results.'. $network_key . '.' . md5($msg['url']),
		AMQP_NOPARAM
		);
	$conn->query("update report_entries set processed=1 where id=?",
		array($id)
		);
	$conn->commit();

}

function process_result($msg, $queue) {
	try {
		load_result($msg->getBody());
		$queue->ack($msg->getDeliveryTag());
	} catch (Exception $e) {
		error_log("Caught exception: " . get_class($e));
		error_log("Message was: " . $e->getMessage());
	}
		
	return true;
}

$q->consume("process_result");

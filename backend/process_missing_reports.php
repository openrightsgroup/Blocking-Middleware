<?php
include_once __DIR__ . "/../api/1.2/libs/DB.php";
include_once __DIR__ . "/../api/1.2/libs/amqp.php";

$ch = amqp_connect();

$ex = new AMQPExchange($ch);
$ex->setName('org.blocked');

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$rs = $conn->query("select report_entries.id, report_id from report_entries left join results on report_entry_id = report_entries.id
where report_entry_id is null", array());

while ($row = $rs->fetch_assoc()) {

    print "Sending {$row['id']}\n";
    $ex->publish((string)$row['id'], 'ooniresults.' . $row['report_id'], AMQP_NOPARAM, array('priority'=>2));

}


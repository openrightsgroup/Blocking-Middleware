<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";
include "$dir/../api/1.2/libs/amqp.php";
include "$dir/../api/1.2/libs/config.php";
$conn = db_connect();

define('MAXQ', 2250);
define('MINQ', 250);


$ch = amqp_connect();

$ex = new AMQPExchange($ch);
$ex->setName('org.blocked');
$ex->setType('topic');
$ex->setFlags(AMQP_PASSIVE);
$ex->declare();

function send_urls($result) {
    global $ex, $conn;

    $c = 0;
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $msg = array('url' => $row[1], 'hash' => $row[2]);
        $ex->publish(json_encode($msg), "url.public", AMQP_NOPARAM);
        $conn->query("update urls set lastpolled = now() where urlid = ?", array($row[0]));
        $c += 1;
    }
    return $c;
}
		
$result = $conn->query("select urlid, url, hash from urls 
	where (lastpolled is null ) and 
	source not in ('social') and status = 'ok' order by lastpolled limit 100", array());

print "Sending URLs (untested)...\n";
$c = send_urls($result);
print "$c urls sent.\n";


$placeholders = implode(",",array_pad(array(), count($REQUEUE_EXCLUDE_SOURCES), "?"));

$result = $conn->query("select urlid, url, hash from urls 
	where (lastpolled < (now() - interval '7 day')) and 
	source not in ($placeholders) and status = 'ok' order by lastpolled limit 100", 
    $REQUEUE_EXCLUDE_SOURCES
    );

print "Sending URLs (previously tested)...\n";
$c = send_urls($result);
print "$c urls sent.\n";

$result = $conn->query("select distinct urlid, url, hash, lastpolled from urls
    inner join isp_reports using (urlID)
    where (lastpolled < (now() - interval '1 day')) and
    urls.status = 'ok' and unblocked = 0 and isp_reports.status <= 'sent' 
    order by lastpolled limit 100", array());

print "Sending URLs (reported)...\n";
$c = send_urls($result);
print "$c urls sent.\n";

/* blocked_dmoz built using:

CREATE TABLE blocked_dmoz(urlid int primary key) engine=InnoDB;

INSERT IGNORE INTO blocked_dmoz(urlid) 
SELECT Urls.urlid 
FROM Urls 
INNER JOIN url_latest_status uls USING (urlid) 
WHERE uls.status = 'blocked' AND urls.status = 'ok' AND source = 'dmoz';

*/

$result = $conn->query("select urlid, url, hash from urls
    inner join blocked_dmoz using (urlID)
    where (lastpolled < (now() - interval '1 day')) 
    order by lastpolled limit 50", array());

print "Sending URLs (blocked dmoz)...\n";
$c = send_urls($result);
print "$c urls sent.\n";

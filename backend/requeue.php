<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";
include "$dir/../api/1.2/libs/amqp.php";
include "$dir/../api/1.2/libs/config.php";
include "$dir/../api/1.2/libs/jobs.php";
$conn = db_connect();

define('MAXQ', 2250);
define('MINQ', 250);


$ch = amqp_connect();

$ex = new AMQPExchange($ch);
$ex->setName('org.blocked');
$ex->setType('topic');
$ex->setFlags(AMQP_PASSIVE);
$ex->declare();

function send_urls($result, $key='url.public') {
    global $ex, $conn;

    $c = 0;
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $msg = array('url' => $row[1], 'hash' => $row[2]);
        $ex->publish(json_encode($msg), $key, AMQP_NOPARAM);
        $conn->query("update urls set lastpolled = now() where urlid = ?", array($row[0]));
        $c += 1;
    }
    return $c;
}

function has_arg($name) {
    if ($_SERVER['argc'] == 1) {
        // no arguments, send all sets
        return true;
    }
    if (in_array($name, $_SERVER['argv'])) {
        return true;
    }
    return false;
}

function send_untested() {
    global $conn;

    $result = $conn->query("select urlid, url, hash from urls 
	where (lastpolled is null ) and 
	source not in ('social') and status = 'ok' order by lastpolled limit 100", array());

    print "Sending URLs (untested)...\n";
    $c = send_urls($result, "check.public");
    print "$c urls sent.\n";
    update_jobs($conn, "requeue_untested", "$c urls sent.");
}

function send_tested() {
    global $conn, $REQUEUE_EXCLUDE_SOURCES;
    $placeholders = implode(",", array_pad(array(), count($REQUEUE_EXCLUDE_SOURCES), "?"));

    $result = $conn->query("select urlid, url, hash from urls 
	where (lastpolled < (now() - interval '7 day')) and 
	source not in ($placeholders) and status = 'ok' order by lastpolled limit 100",
        $REQUEUE_EXCLUDE_SOURCES
    );

    print "Sending URLs (previously tested)...\n";
    $c = send_urls($result);
    print "$c urls sent.\n";
    update_jobs($conn, "requeue_tested", "$c urls sent.");
}

function send_reported() {
    global $conn;
    $result = $conn->query("select distinct urlid, url, hash, lastpolled from urls
        inner join isp_reports using (urlID)
        where (lastpolled < (now() - interval '1 day')) and
        urls.status = 'ok' and unblocked = 0 and (isp_reports.status in ('pending', 'sent', 'unblocked', 'rejected'))
        order by lastpolled limit 55", array());

    print "Sending URLs (reported)...\n";
    $c = send_urls($result, 'url.public.gb');
    print "$c urls sent.\n";
    update_jobs($conn, "requeue_reported", "$c urls sent.");
}

function send_wpplugin() {
    global $conn;
    $result = $conn->query("select distinct urlid, url, hash, lastpolled from urls
        where (lastpolled < (now() - interval '7 day')) and
        urls.status = 'ok' and tags && '{wp-plugin}'
        order by lastpolled limit 55", array());

    print "Sending URLs (wpplugin)...\n";
    $c = send_urls($result, 'url.public.gb');
    print "$c urls sent.\n";
    update_jobs($conn, "requeue_wpplugin", "$c urls sent.");
}

function send_dmoz() {
    global $conn;

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
    update_jobs($conn, "requeue_dmoz", "$c urls sent.");
}

function send_copyright() {
    global $conn;

    /* send copyright blocks for retesting */

    $result = $conn->query("select distinct urls.urlid, url, hash, lastpolled from urls
        inner join url_latest_status using (urlid)
        where url_latest_status.status = 'blocked' and urls.status = 'ok' and blocktype = 'COPYRIGHT' and lastpolled < (now() - interval '7 day')
        order by lastpolled limit 50", array());

    print "Sending URLs (copyright blocked)...\n";
    $c = send_urls($result, "url.public.gb");
    print "$c urls sent.\n";
    update_jobs($conn, "requeue_copyright", "$c urls sent.");
}

if (has_arg("untested")) {
    send_untested();
}
if (has_arg("tested")) {
    send_tested();
}
if  (has_arg("reported")) {
    send_reported();
}
if (has_arg("wpplugin")) {
    send_wpplugin();
}
if (has_arg("dmoz")) {
    send_dmoz();
}
if (has_arg("copyright")) {
    send_copyright();
}

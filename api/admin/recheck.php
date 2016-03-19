<?php

require "../1.2/libs/DB.php";
require "../1.2/libs/amqp.php";
require "../1.2/libs/url.php";
require "../1.2/libs/services.php";

$db = new APIDB($dbhost, $dbuser, $dbpass, $dbname);
$loader = new UrlLoader($db);
$ch = amqp_connect();

session_start();

try {
    $url = $loader->load(normalize_url($_REQUEST['url']));
    
    $urltext = $url['URL'];
    $loader->updateLastPolled($url['urlID']);

    $msgbody = json_encode(array('url'=>$urltext, 'hash'=>md5($urltext)));

    $ex = new AMQPExchange($ch);
    $ex->setName('org.blocked');
    $ex->publish($msgbody, $SUBMIT_ROUTING_KEY, AMQP_NOPARAM, array('priority'=>2));
    $_SESSION['messages'][] = "Scheduled $urltext for check\n";
} catch (UrlLookupError $e) {
    $_SESSION['messages'][] = 'URL not recognised.  To check a new URL, use the main site at <a href="https://www.blocked.org.uk">https://www.blocked.org.uk</a>.';
}
header("Location: index.php");


<?php

require "../1.2/libs/DB.php";

require "template.inc.php";

$db = new APIDB($dbhost, $dbuser, $dbpass, $dbname);


$recent_urls = $db->query("select URL, inserted, lastPolled
from urls
order by urlid desc
limit 10",array());

$twig->display('index.html', array(
    'recent_urls' => new ResultSetIterator($recent_urls)
    ));

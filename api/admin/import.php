<?php

require "template.inc.php";
include_once "../1.2/libs/DB.php";
include_once "../1.2/libs/amqp.php";
include_once "../1.2/libs/services.php";
include_once "../1.2/libs/url.php";

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);
$amqp = amqp_connect();
$loader = new UrlLoader($conn);

$fp = fopen($_FILES['file1']['tmp_name'],'r');
if (!$fp) {
    die ("Cannot open upload file");
}

$exist = 0;
$new = 0;

$q = $conn->query("select count(*) from urls where source = ?",array($_POST['source']));
$row = $q->fetch_row();
if ($row[0] != 0) {
    die ("Source tag already used.");
}

while ($line = fgets($fp)) {
    $url = normalize_url(trim($line));
    try {
        $urldata = $loader->load($url);
        $exist ++; 
        continue;
    } catch (UrlLookupError $e ) {
        $conn->query("insert into urls(url, hash, source, inserted) values (?,?,?,now())",
            array($url, md5($url), $_POST['source']));
        $new++;

    }
}

$twig->display("import.html", array(
    'new' => $new,
    'exist' => $exist
    ));

<?php

require "template.inc.php";
require "../1.2/libs/DB.php";

$db = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$ttl = $db->query("select count(*) as ct from urls where source = ?",
    array($_GET['source']));
$ttlrow = $ttl->fetch_array();

$ttl2 = $db->query("select count(distinct urlid) ct from results 
inner join  urls using (urlid)
where source = ?",
    array($_GET['source']));
$ttl2row = $ttl2->fetch_array();


$res = $db->query("select uls.status, 
    count(distinct urlid) url_count, count(distinct urlid, network_name) block_count
    from urls 
    inner join url_latest_status uls using (urlid)
    where source = ?
    group by uls.status
    order by uls.status",
    array($_GET['source'])
    );

$twig->display("importstatus.html", array(
    'source' => $_GET['source'],
    'ttlrow' => $ttlrow,
    'ttl2row' => $ttl2row,
    'status' => new ResultSetIterator($res)
    ));

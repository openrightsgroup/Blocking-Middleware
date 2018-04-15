<?php

include "../api/1.2/libs/url.php";
include "../api/1.2/libs/DB.php";

$conn = db_connect();

$q = $conn->query("select urlid, url from urls where urlid >= ? and status = 'ok' order by urlid limit 500",
    array($argv[1])
    );
    
$conn->beginTransaction();    
foreach ($q as $row) {
    try {
        $type = categorize_url($row['url']);
    } catch (BadUrlError $e) {
        continue;
    }
    
    if ($type != '') {
        print "Set {$row['url']} ({$row['urlid']}) => {$type}\n";
        continue;
        $q2 = $conn->query("update urls set url_type = ? where urlid = ?",
            array($type, $row['urlid'])
            );
    }
}
$conn->commit();
    
    

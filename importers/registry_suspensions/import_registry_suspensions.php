<?php

require '../api/1.2/libs/config.php';
require '../api/1.2/libs/DB.php';
require '../api/1.2/libs/services.php';

function logmsg($msg) {
    echo strftime("[%Y-%m-%d %H:%M:%S] ") . $msg . "\n";
}

function process_entry($parts) {
    global $conn, $urlservice;

    // parts: url  registry  timestamp

    $urlservice->insert($parts[0], "registry-suspension");
    $url = $urlservice->load($parts[0]);

    $conn->beginTransaction();

    $rs = $conn->query("select id from registry_suspensions where urlid = ? and registry = ?",
                 array($url['urlid'], $parts[1]));
    $row = $rs->fetch();
    if ($row) {
        logmsg("Updating suspension record for {$parts[0]}");
        $conn->query("update registry_suspensions set lastseen=? where id = ?",
                     array($parts[2], $row['id']));
    } else {
        logmsg("Inserting new suspension record for {$parts[0]}");
        $conn->query("insert into registry_suspensions(urlid, registry, created, lastseen) values (?,?,?,?)",
                     array($url['urlid'], $parts[1], $parts[2], $parts[2]));

    }

    $conn->commit();

}


$conn = db_connect();
$urlservice = new UrlLoader($conn);

$fp = fopen("php://stdin", 'r');
if ($fp) {
    while (!feof($fp)) {
        $line = fgets($fp);
	logmsg("Got: " . trim($line));
        if ($line === false) {
            break;
        }
        $parts = explode("\t", trim($line));
        process_entry($parts);
    }
}
fclose($fp);

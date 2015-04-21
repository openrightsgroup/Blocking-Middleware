<?php

include "../api/1.2/libs/services.php";
include "../api/1.2/libs/url.php";
include "../api/1.2/libs/DB.php";

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);
$conn->autocommit(false);

$loader = new UrlLoader($conn);

$fp = fopen($argv[1],'r');
if (!$fp) {
    print "Error opening {$argv[1]}\n";
    exit(1);
}

$c = 0;
while ($data = fgetcsv($fp)) {
    $url = "http://" . $data[1];

    $loader->insert($url, "alexa");

    $c += 1;
    if ($c % 100 == 0) {
        $conn->commit();
    }
    if ($c % 1000 == 0) {
        print "$c\n";
    }
    if ($c >= 100000) {
        break;
    }
}
$conn->commit();
fclose($fp);
print "$c urls imported\n";



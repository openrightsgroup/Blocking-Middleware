<?php

include "../api/1.2/libs/config.php";
include "../api/1.2/libs/DB.php";
include "../api/1.2/libs/amqp.php";
include "../api/1.2/libs/services.php";

function dbg($msg) {
    error_log($msg);
}

function check_queues() {
    $cmd = "rabbitmqctl -q list_queues name messages";
    $proc = popen($cmd, "r");
    $maxlen = 0;
    while ($line = fgets($proc)) {
        if (strpos($line, ".fixed") !== FALSE || strpos($line, "check") !== FALSE) {
            list($name, $len) = str_split(trim($line), "\t");
            $maxlen = $len > $maxlen ? $len : $maxlen;
        }
    }
    pclose($proc);

    return $maxlen;

}

// check queue lengths every $check_interval urls
$check_interval = 100;

// sleep if the queue is longer than queuelen_sleep
$queuelen_sleep = 100;

$fp = fopen($argv[1],"r");
if (!$fp) {
    die ("Unable to open $argv[1]");
}

$conn = db_connect();
$loader = new UrlLoader($conn);

if (@$argv[3]) {
    $offset = $argv[3];
} else {
    $offset = 0;
}

$n = 0;
while ($line = fgetcsv($fp)) {
    $n ++;
    if ($n < $offset) {
        continue;
    }
    if ($line[1] != "co.uk") {
        continue;
    }
    $domain = $line[0] . "." . $line[1];
    $ip = gethostbyname("www.$domain");
    if ($ip == "www.$domain") {
        dbg("lookup of www.$domain failed");
        $ip = gethostbyname($domain);
        if ($ip == $domain) {
            dbg("lookup of $domain failed");
            continue;
        }
    } else {
        $domain = "www.$domain";
    }
    $isnew = $loader->insert("http://$domain", $argv[2]);

    if ($isnew) {
        print "Inserted: $domain\n";
        $msgbody = json_encode(array('url'=>"http://$domain", 'hash'=>md5($domain)));

        $ch = amqp_connect();
        $ex = new AMQPExchange($ch);
        $ex->setName('org.blocked');
        $ex->publish($msgbody, "check.fixed", AMQP_NOPARAM);
    }
    if ($n % $check_interval == 0) {
        while (check_queues() > $queuelen_sleep) {
            sleep(30);
        }
    }
}

<?php


include "../backend/silex/vendor/autoload.php";
include "../api/1.2/libs/config.php";
include "../api/1.2/libs/DB.php";
include "../api/1.2/libs/amqp.php";
include "../api/1.2/libs/services.php";

function dbg($msg, $level=0) {
    global $VERBOSE;
    if ($level || $VERBOSE) {
        error_log($msg);
    }
}

function check_parameter($f) {
    global $opts;

    if (!isset($opts[$f])) {
        print "Parameter error: $f required\n";
        exit(2);
    }
}

function opt($name, $default=null) {
    global $opts;
    return isset($opts[$name]) ? $opts[$name] : $default;
}

$opts = getopt('vf:', array( 'source:', 'start:', 'exchange:', 'key:', 'column:', 'tags:'));

check_parameter('f');
check_parameter('source');
$VERBOSE = isset($opts['v']);

if ($VERBOSE) {
    print_r($opts);
}

if (opt('tags')) {
    $tags = explode(",",$opts['tags']);
} else {
    $tags = null;
}

// begin importing file

if ($opts['f'] == '--') {
    $fp = open("php://stdin","r");
} else {
    $fp = fopen($opts['f'],"r");
}

if (!$fp) {
    die ("Unable to open $opts[f]");
}

$ch = amqp_connect();
$ex = new AMQPExchange($ch);
$ex->setName(opt('exchange'));
$key = opt('key', 'import'); 

$offset = opt('start', 0);
$col = opt('column', 0);

$n = 0;
while ($line = fgetcsv($fp)) {
    $n ++;
    if ($n < $offset) {
        continue;
    }

    if ($VERBOSE) {
        print_r($line);
    }

    $url = $line[$col];

    $msgbody = json_encode(array(
        'url'=>$url, 
        'source' => $opts['source'],
        'tags' => $tags,
        'resolve' => true,
    ));
    dbg("Posting $url with $key");
    $ex->publish($msgbody, $key, AMQP_NOPARAM);

}
fclose($fp);
touch($opts['f'] . ".done");

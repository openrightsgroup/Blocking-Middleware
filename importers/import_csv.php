<?php


$HELP =<<<END

Script to process csv of urls, checking DNS, inserting to database and posting to queue.

Parameters:

-v                      verbose operation
-f <filename>           File to import
--source <str>          Insert URLs with this source/tag

MESSAGE POSTING:

--exchange <str>        Post messages to this exchange
--key <str>             Post messages with this routing key

QUEUE MONITORING:

--watch <regex>         queue(s) to watch
--limit <int>           Pause when queue(s) exceed this message count
--mean|--max            Compare mean/max of queues to limit
--interval <int>        Change queue state every <int> urls
--delay <int>           sleep for <int> seconds when queues are over limit

FILE HANDLING:

--column <int>          CSV column to use for domain; default 0
--start <int>           Skip first <n> in list

RUN MODES:

--status                Print queue monitor status and exit (requires watch, limit, mean|max)
--help                  Display help and exit

END;

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

function opt($name, $default) {
    global $opts;
    return isset($opts[$name]) ? $opts[$name] : $default;
}

function check_queues() {
    global $opts;
    if (!isset($opts['watch'])) {
        return 0;
    }

    $cmd = "rabbitmqctl -q list_queues name messages";
    $proc = popen($cmd, "r");
    $maxlen = 0;
    $total = 0;
    $count = 0;

    $pattern = "/{$opts['watch']}/";
    while ($line = fgets($proc)) {
        $result = preg_match($pattern, trim($line));
        if ($result === 1) {
            $count++;
            list($name, $len) = explode("\t", trim($line));
            dbg("Matched: $name", isset($opts['status']));
            $total += $len;
            $maxlen = $len > $maxlen ? $len : $maxlen;
        }
    }
    pclose($proc);

    if (isset($opts['mean'])) {
        return $total / $count;
    } elseif (isset($opts['max'])) {
        return $maxlen;
    } else {
        return $total;
    }

}

function dns_lookup($name) {
    $ip = gethostbyname($name);
    if ($ip == $name) {
        dbg("lookup of $name failed");
        return FALSE;
    }
    return $ip;
}

$opts = getopt('vf:', array('watch:','limit:', 'source:', 'start:', 'delay:','mean','max','exchange:','key:','interval:','status','help'));

if (isset($opts['help'])) {
    print $HELP;
    exit(0);
}

check_parameter('f');
check_parameter('source');
$VERBOSE = isset($opts['v']);

// check queue lengths every $check_interval urls
$check_interval = opt('interval', 200) ; 
dbg("Check interval: $check_interval");

// sleep if the queue is longer than queuelen_limit
$queuelen_limit = opt('limit', 1000); 

$delay = opt('delay', 30); 

if (isset($opts['status'])) {
    print check_queues() . "\n";
    exit(0);
}

// begin importing file

$fp = fopen($opts['f'],"r");
if (!$fp) {
    die ("Unable to open $opts[f]");
}

$conn = db_connect();
$loader = new UrlLoader($conn);

$offset = opt('start', 0);

$ch = amqp_connect();
$ex = new AMQPExchange($ch);
$ex->setName(opt('exchange', 'org.blocked'));

$key = opt('key', 'check.public'); 

$col = opt('column', 0);

$n = 0;
while ($line = fgetcsv($fp)) {
    $n ++;
    if ($n < $offset) {
        continue;
    }
    $domain = $line[$col] ;

    $ip = dns_lookup("www.$domain");
    if (!$ip) {
        $ip = dns_lookup($domain);
        if (!$ip) {
            continue;
        }
    } else {
        $domain = "www.$domain";
    }
    $isnew = $loader->insert("http://$domain", $opts['source']);

    if ($isnew) {
        dbg( "Inserted: $domain", 1);
        $msgbody = json_encode(array('url'=>"http://$domain", 'hash'=>md5($domain)));

        dbg("Posting $domain with $key");
        $ex->publish($msgbody, $key, AMQP_NOPARAM);
    }
    if ($n % $check_interval == 0) {
        $len = check_queues();
        while ($len > $queuelen_limit) {
            dbg( "Sleeping: len=$len / $queuelen_limit n=$n", 1);
            sleep($delay);
            $len = check_queues();
        }
    }
}
fclose($fp);
touch($opts['f'] . ".done");

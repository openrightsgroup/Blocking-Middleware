<?php


$HELP =<<<END

Script to process csv of urls, checking DNS, inserting to database and posting to queue.

Parameters:

-v                      verbose operation
-f <filename>           File to import
--source <str>          Insert URLs with this source/tag
--tags <str>            Comma-separated tags for this URL
--resolve               Attempt DNS resolution of domains

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
--categorycolumn <int>  Add category from column <int>
--categorynamespace <str>   Add categories with namespace <str>

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

function opt($name, $default=null) {
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

$opts = getopt('vf:', array('watch:','limit:', 'source:', 'start:', 'delay:','mean','max','exchange:','key:','interval:','status','help','column:','categorycolumn:','categorynamespace:','tags:','resolve'));


if (isset($opts['help'])) {
    print $HELP;
    exit(0);
}

check_parameter('f');
check_parameter('source');
$VERBOSE = isset($opts['v']);

if ($VERBOSE) {
    print_r($opts);
}

// work out whether to use an AMQP connection and post URLs
$AMQP = isset($opts['exchange']) || isset($opts['key']);


$delay = opt('delay', 30); 

if (opt('tags')) {
    $tags = explode(",",$opts['tags']);
} else {
    $tags = null;
}
    

if (isset($opts['status'])) {
    print check_queues() . "\n";
    exit(0);
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

$conn = db_connect();
$loader = new UrlLoader($conn);
$catloader = new CategoryLoader($conn);

$offset = opt('start', 0);

if ($AMQP) {
    // check queue lengths every $check_interval urls
    $check_interval = opt('interval', 200) ; 
    dbg("Check interval: $check_interval");

    // sleep if the queue is longer than queuelen_limit
    $queuelen_limit = opt('limit', 1000); 

    $ch = amqp_connect();
    $ex = new AMQPExchange($ch);
    $ex->setName(opt('exchange'));

    $key = opt('key', 'check.public'); 
}

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

    if (strpos(strtolower($url),"http")!==0) {
        $domain = $url;
        $url = "http://$url";
    } else {
        $domain = preg_replace('!^https?://!i', '',  $url);
    }
    
    if (isset($opts['resolve'])) {

        $ip = false;
        # if domain does not start with www., try www. domain
        if (strpos($domain, 'www.') !== 0) { 
            $ip = dns_lookup("www.$domain");
            if ($ip) {
                $domain = "www.$domain";
                $url = "http://$domain";
            }   
        }
        # if not found, try resolving domain
        if (!$ip) {
            $ip = dns_lookup($domain);
            if (!$ip) {
                continue;
            }
        }
    }
    $isnew = $loader->insert($url, $opts['source'], $tags);

    if ($isnew) {
        dbg( "Inserted: $url", 1);


        if ($AMQP) {
            $msgbody = json_encode(array('url'=>$url, 'hash'=>md5($url)));
            dbg("Posting $url with $key");
            $ex->publish($msgbody, $key, AMQP_NOPARAM);
        }
    }
    if (isset($opts['categorycolumn'])) {
        dbg("Adding category: " . $line[opt('categorycolumn')]);
        $catloader->add_url_category($line[opt('categorycolumn')], $opts['categorynamespace'], $url);
    }
    if ($AMQP) {
        if ($n % $check_interval == 0) {
            $len = check_queues();
            while ($len > $queuelen_limit) {
                dbg( "Sleeping: len=$len / $queuelen_limit n=$n", 1);
                sleep($delay);
                $len = check_queues();
            }
        }
    }
}
fclose($fp);
touch($opts['f'] . ".done");

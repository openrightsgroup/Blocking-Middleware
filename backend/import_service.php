<?php


include_once __DIR__ . "/../api/1.2/libs/DB.php";
include_once __DIR__ . "/../api/1.2/libs/amqp.php";
include_once __DIR__ . "/../api/1.2/libs/pki.php";
include_once __DIR__ . "/../api/1.2/libs/exceptions.php";
include_once __DIR__ . "/../api/1.2/libs/services.php";

$opts = getopt('v', array('queue:'));

function dbg($msg, $level=0) {
    global $VERBOSE;
    if ($level || $VERBOSE) {
        error_log($msg);
    }
}

function opt($name, $default) {
    global $opts;
    
    return (isset($opts[$name])) ? $opts[$name] : $default;
}

function flag($flag) {
    global $opts;
    
    return isset($opts[$flag]);
}

function dns_lookup($name) {
    $ip = gethostbyname($name);
    if ($ip == $name) {
        dbg("lookup of $name failed");
        return FALSE;
    }
    return $ip;
}

$ch = amqp_connect();

$q = new AMQPQueue($ch);
$q->setName(opt('queue', 'import'));
$q->setFlags(AMQP_DURABLE);
$q->declare();

$q->bind('org.blocked', opt('queue', 'import') );

$conn = db_connect();
$loader = new UrlLoader($conn);

if (flag('v')) {
    print "Listening on " . opt('queue','import') . "\n";
}

function process_import($msg, $queue) {
  global $loader;
  try {

    $queue->ack($msg->getDeliveryTag());
    $data = (array)json_decode($msg->getBody());

    /*
     * data:
     *
     * url / urls: string/array
     * tags: array
     * source: str
     * resolve: bool (default true)
     */

    $url = $data['url'];
    if (strpos(strtolower($url),"http")!==0) {
        $domain = $url;
        $url = "http://$url";
    } else {
        $domain = preg_replace('!^https?://!i', '',  $url);
    }

    if (@$data['resolve']) {
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
                error_log("Could not resolve $domain");
                return true;
            }
        }
    }

    $isnew = $loader->insert($url, $data['source'], $data['tags']);
    if ($isnew) {
        dbg("Inserted: $url");
    } else {
        dbg("Updated: $url");
    }

  } catch (Exception $e) {
    error_log("process_result failed.");
    error_log("Caught exception: " . get_class($e));
    error_log("Message was: " . $e->getMessage());
  }

  return true;
}

$q->consume("process_import");

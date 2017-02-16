<?php

include_once "config.php";

include_once "exceptions.php";

date_default_timezone_set('UTC');
/* PHPs date functions are unfortunate.  Now that we're in daylight savings
the timestamps that are being passed as message auth tokens are in UTC.  PHP's
strtotime function uses the local time zone (now BST) to parse the timestamps, 
putting them an hour out.

Since all our message timestamps are UTC, we set the default here.  Unfortunately,
the database will use local time.  Something to keep in mind when interpreting results!
*/
	
/*	$memcache = new Memcache;
	$memcache->addServer('127.0.0.1', 11211);
	$MemcacheShard = 0;
*/	
$APIVersion = "1.2";
$Salt = "PASSWORD SALT";	

define('ERR_DUPLICATE', 23505);

function redis_connect($name) {
    if (!defined('REDIS')) {
        return null;
    }
    $redis = new Redis();
    list($host, $port) = explode(":", REDIS);
    if (!$redis->connect($host, $port)) {
        error_log("Failed to connect to ". REDIS);
        return null;
    }
    $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);

    return $redis;
}

class PGConnection extends PDO {
    function autocommit($value) {
        throw new DatabaseError("AutoCommit is not supported");
    }

    function query($sql, $args=null, $fetch_mode=PDO::FETCH_ASSOC) {
        if (is_null($args)) {
            $args = array();
        }
        $q = $this->prepare($sql);
        if (!$q) {
            $err = $this->errorInfo();
            error_log("Prepare Error SQL: $sql");
            throw new DatabaseError($err[2], $err[0]);
        }
        $q->setFetchMode($fetch_mode);
        if (!$q->execute($args)) {
            $err = $q->errorInfo();
            error_log("Execute SQL: $sql");
            throw new DatabaseError($err[2], $err[0]);
        }
        return $q;
    }

}


function db_connect() {
    global $PG_HOST, $PG_USER, $PG_PASS, $PG_DB;

    $connstr = "pgsql:host=$PG_HOST user=$PG_USER password=$PG_PASS dbname=$PG_DB";
    return new PGConnection($connstr);
}

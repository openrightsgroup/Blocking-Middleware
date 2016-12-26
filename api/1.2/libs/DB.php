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

			

class ResultSetIterator implements Iterator {
    /* Compatibility obj for PHP 5.3, so that mysql result sets
    can be used in foreach loops */

    function __construct($rs) {
        #error_log(__METHOD__);
        $this->_rs = $rs;
        $this->num_rows = $rs->num_rows;
    }

    function current() {
        #error_log(__METHOD__);
        return $this->_row;
    }

    function key() {
        #error_log(__METHOD__);
        return $this->_key;

    }

    function next() {
        #error_log(__METHOD__);
        $this->_row = $this->_rs->fetch_assoc();
        $this->_key++;

    }

    function rewind() {
        #error_log(__METHOD__);
        $this->_row = $this->_rs->fetch_assoc();
        $this->_key = 0;
    }

    function valid() {
        #error_log(__METHOD__);
        return !is_null($this->_row);
    }

    /* pass-through other mysqli_result methods */

    function fetch_array($resulttype = MYSQLI_BOTH) {
        return $this->_rs->fetch_array($resulttype);

    }

    function fetch_assoc() {
        return $this->_rs->fetch_assoc();
    }

    function fetch_row() {
        return $this->_rs->fetch_row();
    }

}

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

    function query($sql, $args=null) {
        if (is_null($args)) {
            $args = array();
        }
        $q = $this->prepare($sql, PDO::FETCH_ASSOC);
        if (!$q) {
            throw new DatabaseError($this->errorInfo()[2]);
        }
        if (!$q->execute($args)) {
            throw new DatabaseError($q->errorInfo()[2]);
        }
        return $q
    }

}


function db_connect() {
    global $PG_HOST, $PG_USER, $PG_PASS, $PG_DB;

    return new PGConnection("pgsql:host=$PG_HOST user=$PG_USER password=$PG_PASS dbname=$PG_DB);
}

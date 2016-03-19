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

	function db_connect() {
		global $dbhost, $dbuser, $dbpass, $dbname;
		$conn = mysql_connect($dbhost, $dbuser, $dbpass) or die ('Error connecting to mysql');
		mysql_select_db($dbname);
		return $conn;
	}

	#db_connect();

	class APIDB extends mysqli {

		function query($sql, $args, $mode=MYSQLI_STORE_RESULT) {
			$ret = parent::query($this->escape($sql, $args), $mode);
			if (!$ret) {
			 	throw new DatabaseError($this->error, $this->errno);
			}
			return $ret;
		}

		function get_autocommit() {
			$result = $this->query("select @@autocommit",array());
			$row = $result->fetch_row();
			return $row[0];
		}

		function escape($sql, $args) {
			// a sloppy positional escape function
			// because I really hate having to type escape_string so many times
			$n = 0;
			$startpos = 0;
			// startpos is used to that we don't get confused by escaped data that contains
			// the placeholder
			while (($startpos = strpos($sql, '?', $startpos)) !== false) {
                if (is_null($args[$n])) {
                    // escape_string() seems to parse PHP NULL into 0, which breaks foreign key
                    // constraints when inserted into a nullable FK target column on a child record.
                    // (Or, worse: links the child record to the parent record with ID 0!)
                    //
                    // To avoid this just replace PHP NULL with the string 'NULL' instead.
                    $esc = 'NULL';
                } else {
                    // OK to parse with escape_string()
                    $esc = "'" . $this->escape_string($args[$n]) . "'";
                }
                $sql = substr_replace($sql, $esc, $startpos, 1);
				$n++;
				$startpos += strlen($esc);  // move startpos past the end of the escaped string
			}
			if ($n != count($args)) {
				$c = count($args);
				throw new Exception("APIDB::escape: number of placeholders ($n) does not match number of arguments ($c)");
			}
			return $sql;
		}

	}
			

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

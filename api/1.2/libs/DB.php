<?php
	$dbhost = 'localhost';
	$dbuser = 'db_username';
	$dbpass = 'db_password';
	$dbname = 'DB_NAME';

	include_once "exceptions.php";
	

	define("GOOGLE_API_KEY", "GOOGLE_KEY");
	
	date_default_timezone_set('Europe/London');
	
	$memcache = new Memcache;
	$memcache->addServer('127.0.0.1', 11211);
	$MemcacheShard = 0;
	
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

		function escape($sql, $args) {
			// a sloppy positional escape function
			// because I really hate having to type escape_string so many times
			$n = 0;
			$startpos = 0;
			// startpos is used to that we don't get confused by escaped data that contains
			// the placeholder
			while (($startpos = strpos($sql, '?', $startpos)) !== false) {
				$esc = $this->escape_string($args[$n]) ;
				$sql = substr_replace($sql, "'" .$esc . "'", $startpos, 1);
				$n++;
				$startpos += strlen($esc)+2; // move startpos past the end of the escaped string
			}
			if ($n != count($args)) {
				$c = count($args);
				throw new Exception("APIDB::escape: number of placeholders ($n) does not match number of arguments ($c)");
			}
			return $sql;
		}

	}
			

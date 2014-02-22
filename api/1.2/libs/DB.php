<?php
	$dbhost = 'localhost';
	$dbuser = 'db_username';
	$dbpass = 'db_password';
	$dbname = 'DB_NAME';
	

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

	db_connect();

	function db_escape($conn, $sql, $args) {
		// a sloppy positional escape function
		// because I really hate having to type escape_string so many times
		$n = 0;
		while (strpos($sql, '?') !== false) {
			$sql = str_replace('?', "'" . $conn->escape_string($args[$n]) . "'", $sql);
			$n += 1;
		}
		return $sql;
	}
			
			
			

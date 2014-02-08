<?php
	$dbhost = 'localhost';
	$dbuser = 'db_username';
	$dbpass = 'db_password';
	$conn = mysql_connect($dbhost, $dbuser, $dbpass) or die                      ('Error connecting to mysql');
	
	mysql_select_db('DB_NAME');

	define("GOOGLE_API_KEY", "GOOGLE_KEY");
	
	date_default_timezone_set('Europe/London');
	
	$memcache = new Memcache;
	$memcache->addServer('127.0.0.1', 11211);
	$MemcacheShard = 0;
	
	$APIVersion = "1.1";
	$Salt = "PASSWORD SALT";	

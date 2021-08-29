<?php

/*Sample credentials*/

$API = "http://api:8080/1.2/api.php"; // local test URL
$USER = 'admin@example.com';
$SECRET = 'azbycxdw55';
$AUTH = 'basic';  // set to 'basic' to use basic auth instead of signature

function createSignatureHash($message, $secret) {
	/* Use hmac functions to return signature for message string */
	return hash_hmac('sha512', $message, $secret);
}

function sign($secret, $data, $keys) {
	/* creates a list of values from $data, using $keys as the ordered
	list of keys.  Signs the resulting list using $secret */

	$items = array();
	foreach($keys as $k) {
		$items[] = $data[$k];
	}
	$signdata = implode(":",$items);
	return createSignatureHash($signdata, $secret);
}


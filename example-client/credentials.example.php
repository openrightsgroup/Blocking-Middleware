<?php

/*Sample credentials*/

$API = "http://localhost/1.2"; // local test URL
$USER = 'example@blocked.org.uk';
$SECRET = 'abcdefghijklmnopqrstuvwxyz123';

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


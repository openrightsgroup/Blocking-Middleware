<?php

/*Sample credentials*/

$API = "https://api.bowdlerize.co.uk/1.2"; // proper URL
#$API = "http://localhost/api/1.2"; // local test URL
$USER = 'web@blocked.org.uk';
$SECRET = 'HHHHHHHHHHHHHHHH';

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


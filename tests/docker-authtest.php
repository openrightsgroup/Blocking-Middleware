<?php

/*Sample credentials*/

$API = "http://localhost:8080/1.2/api.php"; // local test URL
$USER = 'admin@example.com';
$SECRET = 'azbycxdw55';

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

function make_request($args, $opts) {
    global $API;

    $qs = http_build_query($args);

    $options = array(
        'http' => array(
            'method' => 'GET',
            'ignore_errors' => '1',
        )
    );
    foreach ($opts as $k => $v) {
        $options['http'][$k] = $v;
    }

    // send it
    $ctx = stream_context_create($options);
    $result = file_get_contents("$API/status/url?$qs", false, $ctx);

    // get the JSON data back from the api
    $urldata = json_decode($result);

    return $urldata;
}

function test_basic_auth() {
    global $USER, $SECRET;

	$args = array(
		'email' => $USER,
		'url' => "http://www.example.com",
	);
    $opts = array(
        'header' => "Authorization: Basic " . base64_encode("$USER:$SECRET")
    );

    $urldata = make_request($args, $opts);

    print "Basic auth : ";
    if ($urldata->success == true) { 
        print "OK\n";
    } else {
        print "FAIL\n";
    }
}

function test_signature_auth() {
    global $USER, $SECRET;

	$args = array(
		'email' => $USER,
		'url' => "http://www.example.com",
	);
    $args['signature'] = createSignatureHash("http://www.example.com", $SECRET);

	$opts = array();
    $urldata = make_request($args, $opts);

    print "Sig   auth : ";
    if ($urldata->success == true) { 
        print "OK\n";
    } else {
        print "FAIL\n";
    }
}
function test_basic_auth_fail() {
    global $USER, $SECRET;

	$args = array(
		'email' => $USER,
		'url' => "http://www.example.com",
	);


    $opts = array(
        'header' => "Authorization: Basic " . base64_encode("$USER:xx$SECRET")
    );

    $urldata = make_request($args, $opts);

    print "Basic auth (fail) : ";
    if ($urldata->success == false) { 
        print "OK\n";
    } else {
        print "FAIL\n";
    }
}

function test_signature_auth_fail() {
    global $API, $USER, $SECRET;
	$args = array(
		'email' => $USER,
		'url' => "http://www.example.com",
	);
    $args['signature'] = createSignatureHash("http://www.example.com", "xx" . $SECRET);

    $urldata = make_request($args, array());

    print "Sig   auth (fail): ";
    if ($urldata->success == false) { 
        print "OK\n";
    } else {
        print "FAIL\n";
    }
}

test_signature_auth();
test_basic_auth();
test_signature_auth_fail();
test_basic_auth_fail();

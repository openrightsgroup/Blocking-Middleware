<?php

/*
A command line script to post a file of URLs to the API.

Pass two parameters: 
filename - a file containing URLs, one per line.
source - a value for the "source" parameter to the API call.

Source is used for categorizing URLs by origin, and by convention 
is used for batching results together on output.

*/

$BATCH_SIZE = 25;
$SLEEP_INTERVAL = 30;

require_once "credentials.php";

if (count($argv) < 3) {
	die("Two arguments required: <filename> and <source>\n");
}

$fp = fopen($argv[1],'r');
if (!$fp) {
	die("Unable to open file: ${argv[1]}\n");
}


$n = 0;
while ($line = fgets($fp)) {
	$url = trim($line);
	print "|$url|\n";

	$n += 1;
	$payload = array(
				'url' => $url, 
				'email' => $USER,
				'source' => $argv[2],
			);
	// Sign it using $USER's secret
	$payload['signature'] = sign($SECRET, $payload, array("url"));
	$content = http_build_query($payload);


	// build the request
	$options = array(
		'http' => array(
			'header' => "Content-type: application/x-www-form-urlencoded\r\n",
			'method' => 'POST',
			'content' => $content,
		)
	);

	// send it
	$ctx = stream_context_create($options);
	$result = file_get_contents("$API/submit/url", false, $ctx);

	// get the JSON data back from the api
	print_r(json_decode($result));

	if ($n % $BATCH_SIZE == 0) {
		print "Sleeping ($n sent).\n";
		sleep($SLEEP_INTERVAL);
	}
	
}
print "Sent $n urls.\n";
fclose($fp);

<?php
/**
 * This helper should be called via ajax. It will call the streaming results 
 * API and forward the JSON rows through to the front end. This approach is 
 * necessary due to cross-domain restrictions in browsers, and to avoid
 * exposing the API credentials to the public.
 */


/* this isn't actually valid JSON - each row is a JSON result! */
header("Content-type: application/json;charset=UTF-8");
header("Connection: close");

// Necessary on the live server to suppress caching so that
// the results update in a timely fashion.
while (@ob_end_clean());

ini_set("output_buffering", "off");
ob_implicit_flush(1);
ob_flush();

include "credentials.php";

$url = $_GET['url'];

$streamurl = "$API/stream/results?";

$args = array(
	"url" => $url,
	"date" => date("Y-m-d H:i:s"),
	"email" => $USER,
	"timeout" => 5,
);
$args['signature'] = sign($SECRET, $args, array("url", "date"));

$streamurl .= http_build_query($args);

if (!($fp = fopen($streamurl, "r"))) {
	print "error";
	die();
}

ob_flush();
$buffer = "";

while (!feof($fp)) {
	$r = array($fp);
	$w = null;
	$e = null;


	// Not sure how likely it is that we'll get a partial line ...*/
	$buffer .= fgets($fp);
	error_log("Read: $buffer");

	// check that we've got an entire json line
	if (substr($buffer, -1, 1) == "\n") {
		if (strlen($buffer) > 1) { // line is not empty
			$data = (array) json_decode($buffer);
			if (!isset($data['type'])) { // ignore non-result lines
				echo $buffer;
				ob_flush();
			}
		}
		$buffer = "";
	}
}
fclose($fp);

?>

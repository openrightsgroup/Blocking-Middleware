<?php

header("Content-type: text/html;charset=UTF-8");
header("Connection: close");
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
$args['signature'] = sign($SECRET, $args, array("url","date"));

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
		$data = (array)json_decode($buffer);
		if (!isset($data['type'])) {
			echo $buffer;
			ob_flush();
		}
		$buffer = "";
	}

}
fclose($fp);
?>
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
	"timeout" => 10,
);
$args['signature'] = sign($SECRET, $args, array("url","date"));

$streamurl .= http_build_query($args);

if (!($fp = fopen($streamurl, "r"))) {
	print "error";
	die();
}
?>
<html>
<head>
<style type="text/css">
.ok {
	background-color: #339933;
	}
.blocked {
	background-color: #993333;
	}
.error {
	background-color: #999933;
	}
div span {
	display: inline-block;
	width: 16em;
	}
</style>
</head>
<body>
<h2>URL: <?php
echo $url;
?></h2>
<?php

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
			#error_log(implode(" ", array_keys($data)));
			print "<div><span>{$data['network_name']}</span><span class=\"{$data['status']}\">{$data['status']}</span></div>\n\n";
			ob_flush();
		}
		$buffer = "";
	}	

}
fclose($fp);
?>
</body>
</html>
<?php



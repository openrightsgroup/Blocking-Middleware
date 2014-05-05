<?php

include_once "credentials.php";

if ($_SERVER['REQUEST_METHOD'] == "GET") {
?>
<html>
<head>
<title>Web Client Test</title>
</head>
<body>
<form method="POST" action="/example-client/example-submit.php">
<div>
URL: <input type="input" name="url" />
</div>
<input type="submit" />
</form>
</html>
<?php
} else {

	// set up POST DATA for the API request
	$payload = array(
				'url' => $_POST['url'], 
				'email' => $USER,
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
	$urldata = json_decode($result);

	// now display
?>
<html>
<head><title>Client Test</title>
</head>
<body>

<h2>
Submission result
</h2>

<div id="results">
<?php
var_dump($urldata);
?>
</div>

</body>
</html>
<?php
}
?>

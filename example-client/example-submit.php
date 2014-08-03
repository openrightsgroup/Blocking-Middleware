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
URL: <input type="input" name="url" /><br>
NAME: <input type="input" name="fullname" /><br>
EMAIL: <input type="input" name="contactemail" /><br>
ALLOWCONTACT <input type="checkbox" name="allowcontact" /><br>
SUBSCRIBEREPORTS <input type="checkbox" name="subscribereports" /><br>
JOINLIST <input type="checkbox" name="joinlist" /><br>
INFORMATION <input type="textarea" name="information" /><br>
</div>
<input type="submit" />
</form>
</html>
<?php
} else {

    // set up POST DATA for the API request

    if (isset($_POST['allowcontact'])) {
        $allowcontact = 1;
    } else {
        $allowcontact = 0;
    }

    if (isset($_POST['subscribereports'])) {
        $subscribereports = 1;
    } else {
        $subscribereports = 0;
    }

    if (isset($_POST['joinlist'])) {
        $joinlist = 1;
    } else {
        $joinlist = 0;
    }

	$payload = array(
        'url' => $_POST['url'],
        'fullname' => $_POST['fullname'],
        'email' => $USER,
        'contactemail' => $_POST['contactemail'],
        'allowcontact' => $allowcontact,
        'subscribereports' => $subscribereports,
        'joinlist' => $joinlist,
        'information' => $_POST['information']
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
			'ignore_errors' => '1',
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

<div id="results"><pre>
<?php
var_dump($urldata);
?>
</pre></div>

</body>
</html>
<?php
}
?>

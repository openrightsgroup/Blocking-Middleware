<?php

include_once "credentials.php";

if ($_SERVER['REQUEST_METHOD'] == "GET") {
?>
<html>
<head>
<title>Web Client Test</title>
</head>
<body>
<form method="POST" action="/example-client/example-user-registration.php">
<div>
EMAIL: <input type="input" name="email" /><br>
PASSWORD: <input type="input" name="password" /><br>
</div>
<input type="submit" />
</form>
</html>
<?php
} else {

    // set up POST DATA for the API request


	$payload = array(
        'email' => $_POST['email'],
        'password' => $_POST['password'],
    );
	// Sign it using $USER's secret
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
	$result = file_get_contents("$API/register/user", false, $ctx);

	// get the JSON data back from the api
	$urldata = json_decode($result);

	// now display
?>
<html>
<head><title>Client Test</title>
</head>
<body>

<h2>
Regitration result
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


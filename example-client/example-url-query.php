<?php

include_once "credentials.php";

if ($_SERVER['REQUEST_METHOD'] == "GET") {
?>
<html>
<head>
<title>Web Client Test</title>
</head>
<body>
<form method="POST" action="/example-client/example-url-query.php">
<div>
URL: <input type="input" name="url" />
</div>
<input type="submit" />
</form>
</html>
<?php
} else {


	$args = array(
		'email' => $USER,
		'url' => $_POST['url'],
		'signature' => createSignatureHash($_POST['url'], $SECRET )
	);
	$qs = http_build_query($args);

	// build the request
	$options = array(
		'http' => array(
			'method' => 'GET',
			'ignore_errors' => '1',
		)
	);

	// send it
	$ctx = stream_context_create($options);
	$result = file_get_contents("$API/status/url?$qs", false, $ctx);

	// get the JSON data back from the api
	$urldata = json_decode($result);

	// now display
?>
<html>
<head><title>Client Test</title>
</head>
<body>

<h2>
Results for <?php echo $_POST['url'] ?>
</h2>

<div id="results">
<?php
if($urldata->success) {
	echo "<table>";
	echo "<tr><th>ISP</th><th>Status</th><th>Status timestamp</th><th>Last blocked timestamp</th><th>First blocked timestamp</th><th>Category</th></tr>";
	foreach($urldata->results as $result) {

	print <<< END
	<tr><td>{$result->network_name}</td><td>{$result->status}</td><td>{$result->status_timestamp}</td><td>{$result->last_blocked_timestamp}</td><td>{$result->first_blocked_timestamp}</td><td>{$result->category}</td></tr><
END;
	}

	echo "</table>";
} else {
	echo "<pre>";
	var_dump($urldata);
	echo "</pre>";
}

?>
</div>

</body>
</html>
<?php
}
?>

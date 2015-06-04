<?php

include_once "credentials.php";



    $dt = date('Y-m-d H:i:s');
	$args = array(
		'email' => $USER,
		'date' => date('Y-m-d H:i:s'),
		'signature' => createSignatureHash($dt, $SECRET )
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
	$result = file_get_contents("$API/status/daily-stats?$qs", false, $ctx);

	// get the JSON data back from the api
	$stats = json_decode($result);

	// now display
?>
<html>
<head><title>Client Test</title>
</head>
<body>

<h2>
Example daily stats
</h2>

<div id="results">
<?php

    print_r($result);

	echo "<pre>";
	var_dump($stats);
	echo "</pre>";

?>
</div>

</body>
</html>

<?php

include_once "credentials.php";

if ($_SERVER['REQUEST_METHOD'] == "GET") {
	$args = array(
		'email' => $USER,
		'url' => $_GET['url'],
		'signature' => createSignatureHash($_GET['url'], $SECRET )
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

    print_r($urldata);

    $networks = array();
    foreach ($urldata->results as $result) {
        if ($result->status == "blocked") {
            $networks[] = array($result->network_name, $result->network_id);
        }

    }
    print_r($networks);

	// now display
?>
<html>
<head><title>ISP Report submission</title>
</head>
<body>

<h2>
Results for <?php echo $_GET['url'] ?>
</h2>

<form action="example-isp-report.php" method="POST">
<div>
Website URL: <input type="hidden" name="url" value="<?=$_GET['url']?>" />
<?=$_GET['url']?>
</div>

<div><h3>Report to networks</h3>
<?foreach ($networks as $network):?>
<div><input type="checkbox" checked name="network[]" value="<?=$network[1]?>" /><?=$network[0]?>
</div>
<?endforeach?>
</div>

<h3>Your information</h3>
<div>Name:
<input type="text" name="name" />
</div>
<div>Email:
<input type="text" name="email" />
</div>

<div>I am:
<div><input type="checkbox" name="user" />
A user of of <?=$_GET['url']?>
</div>
<div><input type="checkbox" name="owner" />
The owner/operator of <?=$_GET['url']?>
</div>
</div>

<h3>About this site</h3>

<div>Why this site should be unblocked:<br />
<textarea rows="5" cols="40" name="message"></textarea>
</div>


<input type="submit" value="Submit" />
</form>

</body>
</html>

<? } else {

// Post methods

    if (count($_POST['network'])==0) {
        print "Error: an ISP report must have one or more networks selected";
        exit();
    }

    $date = date("Y-m-d H:i:s");

    $data = array(
        'url' => $_POST['url'],
        'networks' => $_POST['network'],
        'reporter' => array(
            'name' => $_POST['name'],
            'email' => $_POST['email']
        ),
        'message' => $_POST['message'],
        'date' => $date,
        'auth' => array(
            'email' => $USER,
            'signature' => "",
        )
    );

    $data['auth']['signature'] = createSignatureHash(
        $_POST['url'] . ":" . $date,
        $SECRET
        );



    print_r($data);

    $json = json_encode($data);
	// build the request
	$options = array(
		'http' => array(
			'method' => 'POST',
            'content' => $json,
			'ignore_errors' => '1',
            'header' => 'Content-type: application/javascript'
		)
	);

	// send it
	$ctx = stream_context_create($options);
	$result = file_get_contents("$API/ispreport/submit", false, $ctx);

    print_r($result);

}

?>

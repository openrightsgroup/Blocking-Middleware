<?php

include_once "credentials.php";

if (!isset($_REQUEST['search'])) {
?>
<html>
<head>
<title>Web Client Test</title>
</head>
<body>
<form method="GET" action="/example-client/example-category-search.php">
<div>
Category search term: <input type="input" name="search" />
</div>
<input type="submit" />
</form>
</html>
<?php
} else {


	$args = array(
		'email' => $USER,
        'search' => $_REQUEST['search'],
		'signature' => createSignatureHash($_REQUEST['search'], $SECRET )
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
	$result = file_get_contents("$API/category/search?$qs", false, $ctx);

	// get the JSON data back from the api
	$catdata = json_decode($result);


	// now display
?>
<html>
<head><title>Client Test</title>
</head>
<body>

<h2>
Results for search <?=$_REQUEST['search']?>
</h2>

<div id="results">
<h4>Subcategories</h4>
<table>
<tr><th>Category</th><th>Display name</th><th>Name</th></tr>
<?php foreach($catdata->categories as $cat):?>
<tr>
<td><?=$cat->id?></td>
<td><?=$cat->display_name?></td>
<td><?=$cat->name?></td>
</tr>

<?php endforeach?>
</table>


</div>

</body>
</html>
<?php
}
?>

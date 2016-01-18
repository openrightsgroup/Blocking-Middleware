<?php

include_once "credentials.php";

if (!isset($_REQUEST['cat'])) {
?>
<html>
<head>
<title>Web Client Test</title>
</head>
<body>
<form method="GET" action="/example-client/example-category.php">
<div>
Category ID: <input type="input" name="cat" />
</div>
<p>Enter "0" for a view of toplevel categories</p>
<input type="submit" />
</form>
</html>
<?php
} else {


	$args = array(
		'email' => $USER,
		'signature' => createSignatureHash($_REQUEST['cat'], $SECRET )
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
	$result = file_get_contents("$API/category/$_REQUEST[cat]?$qs", false, $ctx);

	// get the JSON data back from the api
	$catdata = json_decode($result);

    $result2 = file_get_contents("$API/category/sites/$_REQUEST[cat]?$qs", false, $ctx);
    $sitedata = json_decode($result2);

	// now display
?>
<html>
<head><title>Client Test</title>
</head>
<body>

<h2>
Results for <?=$catdata->name?> <?php echo $_REQUEST['cat'] ?>
</h2>

<?php if (isset($catdata->parent)): ?>
<p><a href="<?=$PHP_SELF;?>?cat=<?=$catdata->parent;?>">Parent</a></p>
<?php endif ?>
<div id="results">
<h4>Subcategories</h4>
<table>
<tr><th>Category</th><th>Blocked URL count</th><th>Block count</th></tr>
<?php foreach($catdata->categories as $cat):?>
<tr>
<td><a href="<?=$PHP_SELF?>?cat=<?=$cat->id?>"><?=$cat->name?></a></td>
<td><?=$cat->blocked_url_count?></td>
<td><?=$cat->block_count?></td>
</tr>

<?php endforeach?>
</table>

<h4>Sites</h4>
<table>
<tr><td>Site</td><td>Block Count</td></tr>
<?php foreach($sitedata->sites as $site): ?>
<tr><td><?=$site->url?></td><td><?=$site->block_count?></td></tr>
<?php endforeach?>

</table>

<?php
    /*
	echo "<pre>";
	var_dump($sitedata);
	echo "</pre>";
    */

?>
</div>

</body>
</html>
<?php
}
?>

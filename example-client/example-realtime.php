<html>
<head>
<title>Results table with realtime updates from stream</title>
<script src="jquery-1.11.0.js"></script>
<script src="realtimeresults.js"></script>
<script type="text/javascript">
$(document).ready(function(){
	var url = "<?php echo $_GET['url'] ?>";
	if(url) {
		setTimeout(function () {
			$.post(
				"/example-client/example-js-submit-helper.php",
				$('#submit-form').serialize(),
				function(data, status, xhr) {
					$('#submit').text(data);
					}
				);
		}, 1000);
	}
});


</script>
<style>
	.updated td { background: yellow !important; }
	#results { width: 500px; border-collapse: collapse;}
	td { border: 1px; }
	tr.danger { background: #F2DEDE }
	tr.warning { background: #fcf8e3 }
	tr.success { background: #DFF0D8 }
</style>
</head>
<body class="exampleclient">
<form id="submit-form" method="get">
URL:<input type="text" name="url" value="<?php echo empty($_GET['url']) ? "http://www.example.com" : $_GET['url'] ?>" />
<input type="submit" value="Submit" />
</form>
<form><input type="hidden" id="4DRXNE97LE" value="<?php echo $_GET['url']; ?>"/></form>
<table id="results">
	<thead>
		<tr>
			<th>ISP</th>
			<th>Result</th>
			<th>Last check on</th>
			<th>Last Blocked On</th>
		</tr>
	</thead>
	<tbody>
	<?php
	if(isset($_GET['url'])) {
		include_once "credentials.php";
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

		foreach($urldata->results as $result) {
			echo "<tr class=\"".($result->status == "ok"?($result->last_blocked_timestamp == NULL?"success":"warning"):"danger")."\">";
			echo "<td>{$result->network_name}</td>";
			echo "<td>{$result->status}</td>";
			echo "<td>{$result->status_timestamp}</td>";
			if($result->last_blocked_timestamp == NULL)
				echo "<td>No record of prior block</td>";
			else
				echo "<td>{$result->last_blocked_timestamp}</td>";
			echo "</tr>\n";
		}
	}
	?>
	</tbody>
</table>

</body>
</html>
		

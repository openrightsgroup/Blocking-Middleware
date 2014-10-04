<html>
<head>
<title>Results table with realtime updates from stream</title>
<script src="jquery-1.11.0.js"></script>
<script type="text/javascript">
function updateResultsFromStream(url) {
	var last_response_len = false;
	$("#result").after("<div id=\"loading\">Loading...</div>");
	$.ajax({
		url: "/example-client/example-realtime-helper.php?url=" + url,
		xhrFields: {
			onprogress: function(e) {
				var this_response, response = e.currentTarget.response;
				if(last_response_len === false)
				{
					this_response = response;
					last_response_len = response.length;
				}
				else
				{
					this_response = response.substring(last_response_len);
					last_response_len = response.length;
				}
				jsonRows = this_response.match(/[^\r\n]+/g);
				$(jsonRows).each(function() {
//						console.log(this);
					var notification = JSON.parse(this);
					if(notification.network_name) {
						var isp_tr;
						$("#result tbody tr").each(function() {
							var isp_name = $(this).find("td:first").html();
							if(isp_name == notification.network_name)
								isp_tr = $(this);
						});
						if(isp_tr == undefined) {
							isp_tr = $("<tr>");
							isp_tr.appendTo($("#result tbody"));
						}
						var updated = isp_tr.children("td:nth-child(3)").html() != notification.status_timestamp;
						isp_tr.children().remove();
						isp_tr.attr("class", (updated ? "updated " : "") + (notification.status == "ok"?"success":"danger"));
						isp_tr.append(
								"<td>"+notification.network_name+"</td>"
								+"<td>"+notification.status+"</td>"
								+"<td>"+notification.status_timestamp+"</td>"
								+"<td>"
										+((notification.last_blocked_timestamp == undefined)
								 ? "No record of prior block"
								 : notification.last_blocked_timestamp)
										 +"</td>");
						setTimeout(function() { isp_tr.removeClass("updated") }, 500);
					}
				});
			}
		}
	})
	.done(function(data, status, xhr) {
		$("#loading").remove();
	});
}
$(document).ready(function(){
	var url = "<?php echo $_GET['url'] ?>";
	if(url) {
		$.post(
			"/example-client/example-js-submit-helper.php",
			$('#submit-form').serialize(),
			function(data, status, xhr) {
				$('#submit').text(data);
				}
			);
		updateResultsFromStream(url);
	}
});


</script>
<style>
	.updated td { background: yellow !important; }
	#result { width: 500px; border-collapse: collapse;}
	td { border: 1px; }
	tr.danger { background: #F2DEDE }
	tr.success { background: #DFF0D8 }
</style>
</head>
<body>
<form id="submit-form" method="get">
URL:<input type="text" name="url" value="<?php echo empty($_GET['url']) ? "http://www.example.com" : $_GET['url'] ?>" />
<input type="submit" value="Submit" />
</form>
<table id="result">
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
			echo "<tr class=\"".($result->status == "ok"?"success":"danger")."\">";
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
		

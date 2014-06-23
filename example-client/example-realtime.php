<html>
<head>
<title>Realtime Results foo</title>
<script src="jquery-1.11.0.js"></script>
</head>
<body>
<form id="submit-form">
URL:<input type="text" name="url" value="http://www.example.com" />
<input type="button" id="watch" value="Submit" />
</form>
<div id="submit"></div>
<iframe id="result" style="width: 50%; height: 14em"></iframe>

</div>
<script type="text/javascript">
$(document).ready(function(){
	$('#watch').click(function(){
		var url = "/example-client/example-realtime-helper.php?url=" + escape($('input[name=url]').val());
		$('#result').attr('src', url);

		$.post(
			"/example-client/example-js-submit-helper.php",
			$('#submit-form').serialize(),
			function(data, status, xhr) {
				$('#submit').text(data);
				}
			);
	});
});



</script>
</body>
</html>
		

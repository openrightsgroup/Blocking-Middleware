<html>
	<head>
		<title>Blocked Middleware - Example Client</title>
	</head>
	<body>
		<h1>Example client pages</h1>
		<p>Examples of API calls, submitted via PHP. To see how these work, please
			refer to the PHP source code of these files, and the
			<a href="https://wiki.openrightsgroup.org/wiki/Censorship_Monitoring_Project_API">API docs</a>.</p>
		<p>After creating a user, you will need to set some constants in credentials.php. 
			<code>$USER</code> should be the email address for your user. 
			<code>$SECRET</code> should be the secret you receive on registration.</p>
		<h2>Example files:</h2>
		<ul>
			<?php
			// Add page descriptions here
			$example_descriptions = array(
				"example-realtime.php" => "Submit a URL, retrieve results as they come in via ajax",
				"example-user-registration.php" => "Register a new user",
				"example-submit.php" => "Submit a URL to be tested against ISPs",
				"example-url-query.php" => "Fetch results for a URL");
			
			foreach (glob("example-*.*") as $filename) {
				if(strpos($filename, "helper") === false)
					echo "<li><a href=\"$filename\">$filename</a> - "
						. $example_descriptions[$filename] ."</li>\n";
			}
			?>
		</ul>
	</body>
</html>
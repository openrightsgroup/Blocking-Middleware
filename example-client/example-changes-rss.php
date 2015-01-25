<?php

include "credentials.php";

$dt = date("Y-m-d H:i:s");

$args = array(
	'email' => $USER,
	'date' => $dt,
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
$result = file_get_contents("$API/recent/changes?$qs", false, $ctx);

// get the JSON data back from the api
$urldata = json_decode($result);

header("Content-type: application/xml");

print '<?xml version="1.0"?>
<rss version="2.0">
  <channel>
    <title>Blocked.org.uk Recent Changes</title>
	<link>http://localhost:8080/example-client/example-rss.php</link>
	<language>en</language>
	<ttl>10</ttl>
	<generator>Example-client</generator>
';

foreach($urldata->results as $result) {
$res = (array)$result;
##$tm = strptime($res['created'], "%Y-%m-%d %H:%M:%S");
$tm = strtotime($res['created']);
$ts = strftime("%a, %e %b %Y %H:%M:%S %Z", $tm);
$id = sha1($res['id']);

print "<item>\n";
print "<title>{$res['url']} on {$res['network_name']}</title>\n";
print "<link>https://www.blocked.org.uk/results?url={$res['url']}</link>\n";
print "<description>{$res['url']} on {$res['network_name']} has changed from {$res['old_status']} to {$res['new_status']} ({$res['created']})</description>\n";
print "<pubDate>{$ts}</pubDate>\n";
print "<guid>{$id}</guid>\n";
print "</item>\n";
}

print "</channel>
</rss>
";

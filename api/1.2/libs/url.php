<?php

include_once("exceptions.php");

function normalize_url($url) {

	$parts = parse_url($url);

	#error_log("Got URL parts: " . print_r($parts, true));
	if ($parts === false) {
		throw new BadUrlError("Invalid URL");
	}
	

	if (!isset($parts['scheme'])) {
		# trim off any :/ characters from URL
		$url = "http://" . ltrim($url, ':/');
	}
	$parts = parse_url($url);

	if (!isset($parts['host'])) {
		throw new BadUrlError("No host");
	}

	if (strtolower($parts['scheme']) != 'http' && strtolower($parts['scheme']) != 'https') {
		throw new BadUrlError("Invalid scheme: " . $parts['scheme']);
	}

	return $url;

}

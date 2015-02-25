<?php

include_once("exceptions.php");

function normalize_url($url) {

	$url = trim($url);
	$parts = parse_url($url);

	#error_log("Got URL parts: " . print_r($parts, true));
	if ($parts === false) {
		throw new BadUrlError("Invalid URL");
	}
	

	if (!isset($parts['scheme'])) {
		# trim off any :/ characters from URL
		$url = "http://" . ltrim($url, ':/');
		$parts = parse_url($url);
	}

	if (isset($parts['port'])) {
		throw new BadUrlError("Only HTTP and HTTPS default ports allowed");
	}

	if (@$parts['path'] == '/') {
		# if the url is a bare-domain with a trailing '/', remove the trailing slash
		$url = rtrim($url, '/');
	}

	if (!isset($parts['host'])) {
		throw new BadUrlError("No host");
	}

	if (strtolower($parts['scheme']) != 'http' && strtolower($parts['scheme']) != 'https') {
		throw new BadUrlError("Invalid scheme: " . $parts['scheme']);
	}

	return strtolower($parts['scheme']) . '://' . mb_strtolower($parts['host']) . $parts['path'] . (isset($parts['query']) ? '?'. $parts['query'] : '');

}

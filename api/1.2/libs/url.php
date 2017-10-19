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

    # normalize case - domain and scheme to lower case, path preserves case

    # find first separater after the scheme; allow length 8 for https://
    $seppos = strpos($url, "/", 8);
    if ($seppos === FALSE) {
        # no path separator, entire string can be lower-cased
        return strtolower($url);
    } else {
        # split string into domain and path
        $domain = substr($url, 0, $seppos);
        $path = substr($url, $seppos);
        # recombine string
        return strtolower($domain) . $path;
    }


}

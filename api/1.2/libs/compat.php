<?php

# http_response_code function for php < 5.4.0

$_http_messages = array(
	200 => "OK",
        201 => "Created",
	400 => "Bad Request",
	403 => "Forbidden",
	404 => "Not Found",
        409 => "Conflict",
	500 => "Internal Server Error",
	);

if (!function_exists('http_response_code')) {
	function http_response_code($code) {
		header($_SERVER['SERVER_PROTOCOL'] . " $code {$_http_messages[$code]}");
	}
}

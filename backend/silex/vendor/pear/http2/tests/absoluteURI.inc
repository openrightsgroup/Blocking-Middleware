<?php

/**
 * Generates the output for absoluteURI-related tests
 *
 * This file is called by other phpt files to run the same tests on
 * different $_ENV settings.
 *
 * @category HTTP
 * @package  HTTP2
 * @author   Philippe Jausions <jausions@php.net>
 * @version  $Id$
 */
require_once 'HTTP2.php';

// For 4th argument of HTTP2::absoluteURI() method
if (!defined('HTTP2_RELATIVETOSCRIPT')) {
    define('HTTP2_RELATIVETOSCRIPT', true);
}

$tests = array(
    // page, protocol, port
    array(null, null, null),           // Current full URI
    array('?new=value', null, null),   // Append/replace query string
    array('#anchor', null, null),      // Anchor target to URI
    array('/page.html', null, null),   // Web root
    array('page.html', null, null),    // Relative
    array('page.html', 'http', null),  // Force HTTP
    array('page.html', 'http', 80),    // Force HTTP / default port
    array('page.html', 'http', 8080),  // Force HTTP / port 8080
    array('page.html', 'https', null), // Force HTTPS
    array('page.html', 'https', 443),  // Force HTTPS / default port
    array('page.html', null, 8080),    // Switch port (same protocol)
    array('page.html', 'https', 8888), // Force HTTPS / port 8888
    );

$http = new HTTP2();
foreach ($tests as $test) {
    list($page, $protocol, $port) = $test;

    echo sprintf('%-20s', implode('|', $test)).' => '
         .$http->absoluteURI($page, $protocol, $port, HTTP2_RELATIVETOSCRIPT)
         ."\n";
}

?>
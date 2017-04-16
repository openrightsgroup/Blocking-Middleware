--TEST--
negotiateLanguage()
--GET--
--ENV--
HTTP_HOST=example.org
SERVER_NAME=example.org
QUERY_STRING=
SERVER_PORT=80
HTTPS=off
REQUEST_URI=/subdir/test.php
SCRIPT_NAME=/subdir/test.php
HTTP_ACCEPT_LANGUAGE=es-ES;q=0.7, es; q=0.6 ,fr; q=1.0, en; q=0.5,dk , fr-CH
--FILE--
<?php
/**
 * This test checks for language negotiation
 *
 * PHP version 5
 *
 * @category HTTP
 * @package  HTTP2
 * @author   Philippe Jausions <jausions@php.net>
 * @license  http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @link     http://pear.php.net/package/HTTP2
 */
require_once 'HTTP2.php';

// The --ENV-- Accept-language gives the following order
// (preferred language first)
// 1. dk
// 2. fr
// 3. es-ES
// 4. es
// 5. en

$sets = array( 
    1 => array(
        'es-ES' => 'x',
        'fr' => true,
        'es' => 1,
        'en' => true,
        'dk' => true,
    ),
    2 => array(
        'es-ES' => 'x',
        'fr' => true,
        'es' => 1,
        'en' => true,
    ),
    3 => array(
        'es-ES' => 'x',
        'es' => 1,
        'en' => true,
    ),
    4 => array(
        'es' => 1,
        'en' => true,
    ),
    5 => array(
        'en' => true,
    ),
    6 => array(
        'pt-BR' => true,
        'dk' => false,      // FALSE makes "dk" entry ignored
    ),
    7 => array(
    ),
);
$http = new HTTP2();
foreach ($sets as $i => $languages) {
    echo $i.' => '. $http->negotiateLanguage($languages, 'de')."\n";
}

?>
--EXPECT--
1 => dk
2 => fr
3 => es-ES
4 => es
5 => en
6 => de
7 => de

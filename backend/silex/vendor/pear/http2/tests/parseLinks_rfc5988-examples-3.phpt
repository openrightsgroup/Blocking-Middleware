--TEST--
parseLinks(): RFC 5988 example 3
--FILE--
<?php
/**
 * This test checks for link parsing
 *
 * PHP version 5
 *
 * @category HTTP
 * @package  HTTP2
 * @author   Philippe Jausions <jausions@php.net>
 * @license  http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @link     http://pear.php.net/package/HTTP2
 */
require_once  'HTTP2.php';
$http = new HTTP2();
$out = var_export(
     $http->parseLinks(
        '</TheBook/chapter2>;rel="previous"; title*=UTF-8\'de\'letztes%20Kapitel,'
     ),
     true
);
echo preg_replace('#\s+\n#', "\n", $out);
?>
--EXPECT--
array (
  0 =>
  array (
    '_uri' => '/TheBook/chapter2',
    'rel' =>
    array (
      0 => 'previous',
    ),
    'title*' =>
    array (
      'de' => 'letztes Kapitel',
    ),
  ),
)

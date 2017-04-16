--TEST--
parseLinks(): RFC 5988 example 2
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
$out = var_export($http->parseLinks('</>; rel="http://example.net/foo"'), true);
echo preg_replace('#\s+\n#', "\n", $out);
?>
--EXPECT--
array (
  0 =>
  array (
    '_uri' => '/',
    'rel' =>
    array (
      0 => 'http://example.net/foo',
    ),
  ),
)

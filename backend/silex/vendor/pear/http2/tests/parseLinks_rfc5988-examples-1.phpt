--TEST--
parseLinks(): RFC 5988 example 1
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
$links = array(
    '<http://example.com/TheBook/chapter2>; rel="previous"; title="previous chapter"',
);
$out = var_export($http->parseLinks($links), true);
echo preg_replace('#\s+\n#', "\n", $out);
?>
--EXPECT--
array (
  0 =>
  array (
    '_uri' => 'http://example.com/TheBook/chapter2',
    'rel' =>
    array (
      0 => 'previous',
    ),
    'title' => 'previous chapter',
  ),
)

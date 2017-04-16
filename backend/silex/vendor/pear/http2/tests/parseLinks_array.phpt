--TEST--
parseLinks(): link value array
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
         array(
             '<>;a=b;b=c;c=d;d="e"',
             '<#>,<##>;'
         )
     ),
     true
);
echo preg_replace('#\s+\n#', "\n", $out);
?>
--EXPECT--
array (
  0 =>
  array (
    '_uri' => '',
    'a' => 'b',
    'b' => 'c',
    'c' => 'd',
    'd' => 'e',
  ),
  1 =>
  array (
    '_uri' => '#',
  ),
  2 =>
  array (
    '_uri' => '##',
  ),
)

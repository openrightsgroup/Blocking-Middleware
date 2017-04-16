--TEST--
parseLinks(): RFC 5988 example 4
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
    '</TheBook/chapter4>; rel="next"; title*=UTF-8\'de\'n%c3%a4chstes%20Kapitel,'
    . '<http://example.org/>;rel="start http://example.net/relation/other"',
);
$out = var_export($http->parseLinks($links), true);
echo preg_replace('#\s+\n#', "\n", $out);
?>
--EXPECT--
array (
  0 =>
  array (
    '_uri' => '/TheBook/chapter4',
    'rel' =>
    array (
      0 => 'next',
    ),
    'title*' =>
    array (
      'de' => 'nächstes Kapitel',
    ),
  ),
  1 =>
  array (
    '_uri' => 'http://example.org/',
    'rel' =>
    array (
      0 => 'start',
      1 => 'http://example.net/relation/other',
    ),
  ),
)

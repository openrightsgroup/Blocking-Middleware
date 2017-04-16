--TEST--
parseLinks(): real world examples
--FILE--
<?php
require_once  'HTTP2.php';
$http = new HTTP2();
$links = array(
    '<http://waterpigs.co.uk/mentions/webmention/>; rel="http://webmention.org/"',
    '<http://aaronparecki.com/webmention.php>; rel="http://webmention.org/"',
    '<https://indieauth.com>; rel="indieauth"'
);
$out = var_export($http->parseLinks($links), true);
echo preg_replace('#\s+\n#', "\n", $out);
?>
--EXPECT--
array (
  0 =>
  array (
    '_uri' => 'http://waterpigs.co.uk/mentions/webmention/',
    'rel' =>
    array (
      0 => 'http://webmention.org/',
    ),
  ),
  1 =>
  array (
    '_uri' => 'http://aaronparecki.com/webmention.php',
    'rel' =>
    array (
      0 => 'http://webmention.org/',
    ),
  ),
  2 =>
  array (
    '_uri' => 'https://indieauth.com',
    'rel' =>
    array (
      0 => 'indieauth',
    ),
  ),
)

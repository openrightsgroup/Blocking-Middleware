--TEST--
parseLinks(): relation without quotes
--FILE--
<?php
require_once  'HTTP2.php';
$http = new HTTP2();
$links = array(
    '<http://wp.me/PEmnE-1hd>; rel=shortlink'
);
$out = var_export($http->parseLinks($links), true);
echo preg_replace('#\s+\n#', "\n", $out);
?>
--EXPECT--
array (
  0 =>
  array (
    '_uri' => 'http://wp.me/PEmnE-1hd',
    'rel' =>
    array (
      0 => 'shortlink',
    ),
  ),
)

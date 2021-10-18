<?php

require "../api/1.2/libs/services.php";

$service = new IpLookupService(null);

$test = $service->normalize_ipv6("2a02:8011:4003:0b01::11");
print($test . "\n");
assert($test == "2a02801140030b01");
$test = $service->normalize_ipv6("2a02:8011:4003:1:b1::11");
print($test . "\n");
assert($test == "2a0280114003000100b1");

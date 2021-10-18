<?php

require "../api/1.2/libs/services.php";

$service = new IpLookupService(null);

$test = $service->normalize_ipv6("2a02:8011:4003:1001::11");
assert($test == "2a028011400310010000000000000011");
$test = $service->normalize_ipv6("2a02:8011:4003:1:1::11");
assert($test == "2a028011400300010001000000000011");

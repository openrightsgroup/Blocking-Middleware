<?php

include "../api/1.2/libs/url.php";
include "../api/1.2/libs/DB.php";
include "../api/1.2/libs/services.php";

$conn = db_connect();
$loader = new UrlLoader($conn);

$conn->beginTransaction();
$url = $loader->load($argv[1]);
$loader->update_url_hierarchy($url);
$conn->commit();




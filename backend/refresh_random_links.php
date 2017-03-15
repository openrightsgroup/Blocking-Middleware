<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";
include "$dir/../api/1.2/libs/services.php";
include "$dir/../api/1.2/libs/amqp.php";
$conn = db_connect();
$redis = redis_connect("cache");

$key = "randomlinks";

$loader = new UrlLoader($conn);

if ($redis->lLen($key) < $argv[2]) {

    $links = $loader->get_unreported_blocks($argv[1]);

    foreach ($links as $url) {
        $redis->rPush($key, $url);
    }

}

$key = "randomcat";
$loader = new DMOZCategoryLoader($conn);

if ($redis->lLen($key) < $argv[2]) {

    $cats = $loader->random($argv[1]);

    foreach ($cats as $cat) {
        $redis->rPush($key, $cat);
    }

}

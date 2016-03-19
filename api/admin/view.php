<?php

require "page.inc.php";
include "../1.2/libs/amqp.php";
$ch = amqp_connect();

$networks = array(
'vodafone' => 'Vodafone',
'o2' => 'O2',
'ee' => 'EE',
'aaisp' => 'AAISP',
'bt' => 'BT',
'talktalk' => 'TalkTalk',
'plusnet' => 'PlusNet',
'sky' => 'Sky'

);

$url = $_POST['url'];
$id = md5($url);
$hash = md5($url);
$msgbody = json_encode(array('url' => $url, 'hash' => $id));

foreach ($networks as $network => $name) {
    $q = new AMQPQueue($ch);
    $q->setName("view.$network.$hash");
    $q->setArgument("x-expires", 25000);
    $q->declare();
    $q->bind("org.blocked", "admin.results.$network.$hash");
}

$ex = new AMQPExchange($ch);
$ex->setName('org.blocked');
if ($url) {
    $ex->publish($msgbody, "admin.view.$id", AMQP_NOPARAM, array('priority'=>2));
}

$twig->display("view.html", array(
    'url' => $url,
    'networks' => $networks,
    'id' => $id
    ));

<?php

require "../1.2/libs/DB.php";
require "template.inc.php";

$db = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$res = $db->query("select email, fullname, status, createdat from users order by id desc");


$twig->display("users.html", array(
   'users' => new ResultSetIterator($res)
   ));

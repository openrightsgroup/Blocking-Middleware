<?php

session_start();

require "../../backend/silex/vendor/autoload.php";
$loader = new Twig_Loader_Filesystem("templates");
$twig = new Twig_Environment($loader, array(
    'cache' => false,
    'debug' => true
));
$twig->addExtension(new Twig_Extension_Debug());

$twig->addGlobal('request_uri', $_SERVER['REQUEST_URI']);
$twig->addGlobal('php_self', $_SERVER['PHP_SELF']);

$twig->addGlobal('messages', $_SESSION['messages']);
$_SESSION['messages'] = array();


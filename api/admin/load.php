<?php

require "../1.2/libs/DB.php";
require "template.inc.php";

$db = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$recent = $db->query("select source, max(urlid), max(inserted)
    from urls 
    where source not in ('user','dmoz')
    group by source
    having max(inserted) > date_sub(now(), interval 30 day)
    order by max(inserted) desc",
    array()
    );

$twig->display('load.html',array(
    'recent' => new ResultSetIterator($recent)
    ));
    

<?php

require "../1.2/libs/DB.php";

$db = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$mode = $_GET['mode'];
$source = $_GET['source'];
$dt = date('Ymd_His');

header("Content-disposition: attachment; filename=${source}_${mode}_${dt}.csv");
header("Content-type: text/csv");

$fp = fopen('php://output', 'w');

if ($mode == "full") {

    $res = $db->query("select url, network_name, results.status, results.created,
        category, blocktype
        from urls inner join results using (urlid)
        where source = ? 
        order by url, network_name, results.created", array($source)
        );

    fputcsv($fp, array("URL","Network","Status","Created","Category","Block type"));
    while ($row = $res->fetch_row()) {
        fputcsv($fp, $row);
    }

} elseif ($mode == 'latest') {

    $res = $db->query("select url, network_name, uls.status, uls.created,
        category, blocktype
        from urls inner join url_latest_status uls using (urlid)
        where source = ? 
        order by url, network_name, uls.created", array($source)
        );

    fputcsv($fp, array("URL","Network","Status","Created","Category","Block type"));
    while ($row = $res->fetch_row()) {
        fputcsv($fp, $row);
    }

} elseif ($mode == 'table') {

    $nets = $db->query("select distinct network_name from
        results inner join urls using (urlid)
        where source=? order by network_name", 
        array($source)
        );
    $networks = array();
    while ($row = $nets->fetch_row()) {
        $networks[] = $row[0];
    }

    $res = $db->query("select url, network_name, results.status
        from urls inner join results using (urlid)
        where source = ? 
        order by url, network_name, results.created", 
        array($source)
        );

    fputcsv($fp, array_merge(array("URL"), $networks));

    $row = $res->fetch_row();
    $last = $row[0];
    do {
        $result = array();
        do {
            $result[$row[1]] = $row[2];

            $row = $res->fetch_row();
            if (!$row) {
                break;
            }
        } while($row[0] == $last) ;
        $out = array($last);
        foreach ($networks as $net) {
            $out[] = $result[$net];
        }
        fputcsv($fp, $out);
        $last = $row[0];
    } while ($row);

}

fclose($fp);

?>


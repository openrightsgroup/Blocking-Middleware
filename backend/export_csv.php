<?php


include_once __DIR__ . "/../api/1.2/libs/DB.php";

#$PG_HOST='localhost'; # use local DB

$conn = db_connect();

$exclude_tags = "{uk-zone,com-zone,dotorg,dot-uk-zone,me-uk-zone,misc-uk-zone,org-uk-zone}";

$urllist = $conn->query(
    "select urlid, tags from urls
    where not (urls.tags::varchar[] <@ ?::varchar[]) and urls.status = 'ok'
    order by urlid",
    array($exclude_tags)
);

if (count($argv) == 1) {
    $filename = null;
    $fp = fopen('php://stdout','w');
} else {
    $filename = $argv[1];
    $fp = fopen($filename,'w');
}
$stderr = fopen('php://stderr', 'w');

fputcsv($fp, array('# Latest results per URL/Network'));
fputcsv($fp, array('URL','URL Submission Timestamp','Network Name','Status','Result Timestamp','Block Category','Block Type'));

while (true) {
    $ids = array();
    for ($i = 0; $i < 50; $i++) {
        $urlids = $urllist->fetch(PDO::FETCH_NUM);
        if (is_null($urlids)) {
            break;
        }
        #fputs($stderr, "URL: {$urlids['urlid']}, tags: {$urlids['tags']}");
        $ids[] = $urlids[0];
    }
    if (count($ids) == 0) {
        break;
    }
    $placeholders = array_pad(array(), count($ids), '?');
    $placeholders = implode(',', $placeholders);
    $result = $conn->query(
        "select url, urls.inserted url_submitted, network_name, uls.status, uls.created,  uls.category, uls.blocktype 
        from 
        url_latest_status uls
        inner join urls using (urlid)
        inner join isps on network_name = isps.name
        where regions && '{gb}'::varchar[] and urlid in ($placeholders)
        order by urlid, network_name, uls.created",
        array($placeholders)
    );
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        #print implode($row, "\t") . "\n";
        fputcsv($fp, $row);
    }
}


fclose($fp);


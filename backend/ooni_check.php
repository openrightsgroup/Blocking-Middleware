<?php

# long running MySQL connections
ini_set('mysqli.reconnect',1);

include_once __DIR__ . "/../api/1.2/libs/DB.php";
include_once __DIR__ . "/../api/1.2/libs/amqp.php";
include_once __DIR__ . "/../api/1.2/libs/url.php";
include_once __DIR__ . "/../api/1.2/libs/pki.php";
include_once __DIR__ . "/../api/1.2/libs/exceptions.php";
include_once __DIR__ . "/../api/1.2/libs/services.php";

include_once "ooni_test.php";

# Summarize results with greater level of detail
# re-parses the OONI result data, so may be slow

$conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);

$res = $conn->query("
select results.status, results.network_name, data, domain, report_entry_id, source, results.created from
report_entries inner join results on report_entry_id = report_entries.id
inner join urls using (urlid)
", MYSQLI_USE_RESULT);

while ($row = $res->fetch_array()) {
    $data = yaml_parse($row[2]);

    $tor = find_tor($data);
    $dirty = find_dirty($data);

    $code_tor = $tor['response']['code'];
    $code_dirty = $dirty['response']['code'];
    if ($row[0] == 'error') {
        continue;
    }
    #if ($code_tor != $code_dirty || (!is_null($data['body_length_match']) && !$data['body_length_match'])) {
        $url = str_pad($row[3], 24);
        $net = str_pad($row[1], 16);

        $comp = "";
        if (substr($code_tor,0,1) == '3' && substr($code_dirty, 0, 1) == '3') {
            $loc_tor = find_header($tor, "Location");
            $loc_dirty = find_header($dirty, "Location");
            if ($loc_tor != $loc_dirty) {
                $comp = "!! $loc_tor  $loc_dirty";
            } 
        }

        $dt = explode(' ', $row[6]); $dt = $dt[0];

    print "{$url}\t{$row[4]}\t{$row[5]}\t{$dt}\t{$row[0]}\t{$net}\t{$code_tor}\t{$code_dirty}\t{$data['body_length_match']}\t{$comp}\n";
    #}
}

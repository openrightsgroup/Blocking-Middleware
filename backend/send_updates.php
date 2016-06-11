<?php

include "../api/1.2/libs/DB.php";
include "../api/1.2/libs/services.php";

include_once __DIR__ . "/silex/vendor/autoload.php";

$loader = new Twig_Loader_Filesystem(__DIR__ . "/templates");
$twig = new Twig_Environment($loader, array(
    'cache' => false,
    'debug' => true
));
$twig->addExtension(new Twig_Extension_Debug());


$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

function send_update($contact, $results) {
    global $twig, $conn;

    var_dump($contact);
    var_dump($results);

    if (count($results) == 0) {
        return;
    }

    print $twig->render(
        'blocking_update.txt',
        array(
            'fullname' => $contact[0],
            'date' => date('Y-m-d'),
            'results' => $results
            )
        );

    $conn->query("update url_subscriptions set last_notification = now() 
        where contactID = ? and verified = 1",
        array($contact[2])
        );
}

$r = $conn->query("select url_subscriptions.*,
    fullName,email,
    url
    from url_subscriptions 
    inner join contacts on contactID = contacts.id
    inner join urls using (urlID)
    where url_subscriptions.verified = 1 order by contactID",array());


$last = null;
$contact = null;
while ($sub = $r->fetch_assoc()) {
    // group subscriptions by contact
    if ($last != $sub['contactID']) {

        if (!is_null($last)) {
            // format report, send email, flush results bucket
            send_update($contact, $results);
        }

        $results = array();
        $last = $sub['contactID'];
        $contact = array($sub['fullName'], $sub['email'], $sub['contactID']);
    }

    if (is_null($sub['last_notification'])) {
        $lastnotify = date('Y-m-d', time() - 86400); // yesterday
    } else {
        $lastnotify = $sub['last_notification'];
    }

    $r2 = $conn->query("select * from url_status_changes
        where urlID = ? and created >= ? 
        and old_status is not null
        order by network_name, created",
        array($sub['urlID'], $lastnotify));

    while ($data = $r2->fetch_assoc()) {
        print_r($data);
        $results[$sub['url']][$data['network_name']][] = array(
            'old_status' => $data['old_status'],
            'new_status' => $data['new_status'],
            'created' => $data['created']
            );
    }
}
if (count($results)) {
    send_update($contact, $results);
}

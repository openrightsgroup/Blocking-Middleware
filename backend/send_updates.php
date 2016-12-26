<?php

include __DIR__ . "/../api/1.2/libs/DB.php";
include __DIR__ . "/../api/1.2/libs/services.php";

include_once __DIR__ . "/silex/vendor/autoload.php";

$loader = new Twig_Loader_Filesystem(__DIR__ . "/templates");
$twig = new Twig_Environment($loader, array(
    'cache' => false,
    'debug' => true
));
$twig->addExtension(new Twig_Extension_Debug());

if (@$argv[1] == "--help") {
    print "send_updates.php [-n]
    -n      Dummy mode;  print output email without sending

";
    exit(0);
}

$date = date('Y-m-d');

$conn = db_connect();

function send_update($contact, $results) {
    global $twig, $conn, $argv, $date;

    var_dump($contact);
    #var_dump($results);

    if (count($results) == 0) {
        return;
    }

    $msg = new PHPMailer();
    $msg->setFrom(SITE_EMAIL, SITE_NAME);
    $msg->addAddress($contact[1], $contact[0]);
    $msg->Subject = "Blocked.org.uk site updates :: " . date('Y-m-d');
    $msg->isHTML(false);
    $msg->CharSet = 'utf-8';
    $msg->Body = $twig->render(
        'blocking_update.txt',
        array(
            'fullname' => $contact[0],
            'date' => $date,
            'results' => $results
            )
        );

    print $msg->Body . "\n";

    if (@$argv[1] == "-n") {
        return;
    }

    if (!$msg->Send()) {
        print "Unable to send message : " . $msg->ErrorInfo . "\n";
        return;
    }

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
while ($sub = $r->fetch()) {
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

    while ($data = $r2->fetch()) {
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

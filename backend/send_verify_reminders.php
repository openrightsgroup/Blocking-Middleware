<?php


include_once __DIR__ . "/../api/1.2/libs/DB.php";
include_once __DIR__ . "/../api/1.2/libs/exceptions.php";
include_once __DIR__ . "/../api/1.2/libs/services.php";
include_once __DIR__ . "/../api/1.2/libs/email.php";

include_once __DIR__ . "/silex/vendor/autoload.php";

// Send user verification reminders at 1 day, 1 week interval.

$conn = db_connect();
$contactloader = new ContactLoader($conn);

$loader = new Twig_Loader_Filesystem(__DIR__ . "/../api/1.2/templates");
$renderer = new Twig_Environment($loader, array(
    'cache' => false,
    'debug' => true
));

/* send verification reminder 1 after 1 day */

$q = $conn->query("select id, fullname, email, token, verify_attempts, verify_last_attempt
    from contacts
    where verified = 0 and verify_attempts = 1 and verify_last_attempt < now() - interval '1 day'",array());
    
foreach ($q as $row) {    
    print "Sending 1day reminder for {$row['email']} : ${row['verify_attempts']} {$row['verify_last_attempt']}\n";
    
    sendUserVerification($row['email'], $row['fullname'], $row['token'], $row['verify_attempts']+1, $renderer);
    $contactloader->set_verify_attempt($row['email'], $row['verify_attempts']+1);
}

/* send verification reminder 2 after 1 week */

$q = $conn->query("select fullname, email, token, verify_attempts, verify_last_attempt
    from contacts
    where verified = 0 and verify_attempts = 2 and verify_last_attempt < now() - interval '7 day'",array());
    
foreach ($q as $row) {
    print "Sending 7day reminder for {$row['email']} : ${row['verify_attempts']} {$row['verify_last_attempt']}\n";
    
    sendUserVerification($row['email'], $row['fullname'], $row['token'], $row['verify_attempts']+1, $renderer);
    $contactloader->set_verify_attempt($row['email'], $row['verify_attempts']+1);
}

/* cancel pending requests after 14 days */

$q = $conn->query("select id, fullname, email, token, verify_attempts, verify_last_attempt
    from contacts
    where verified = 0 and verify_attempts = 3 and verify_last_attempt < now() - interval '7 day'",array());
    
foreach ($q as $row) {
    // cancel requests made by this contact.
    print "Cancelling requests made by {$row['email']} : ${row['verify_attempts']} {$row['verify_last_attempt']}\n";
    
    $conn->query("update isp_reports set status = ? where contact_id = ?",
        array('cancelled', $row['id']));

    $contactloader->set_verify_attempt($row['email'], 4);
}
    
    

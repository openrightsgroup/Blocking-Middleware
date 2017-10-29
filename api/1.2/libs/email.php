<?php

function sendISPReport($name, $email, $network, $url, $message, $report_type, $renderer) {
    $msg = new PHPMailer();
    $msg->AddReplyTo($email, $name);
    $msg->setFrom(SITE_EMAIL, $name . ' via Blocked.org.uk');
    $msg->Sender = SITE_EMAIL;
    $msg->addBCC(SITE_EMAIL);
    $msg->addAddress($network['admin_email'], $network['admin_name']);
    $msg->Subject = "Website blocking enquiry - " . $url;
    #$msg->addCustomHeader("Auto-Submitted", "auto-generated");
    $msg->isHTML(false);
    $msg->CharSet = 'utf-8';
    $msg->Body = $renderer->render(
        'report_email.txt',
        array(
            'reporter_email' => $email,
            'reporter_name' => $name,
            'url' => $url,
            'message' => $message,
            'report_type' => explode(",", $report_type)
            )
        );

    if(!$msg->send()) {
        error_log("Unable to send message: " . $msg->ErrorInfo);
        return false;
    }
    return true;
}

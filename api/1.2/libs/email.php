<?php

function sendISPReport($mailname, $name, $email, $network, $url, $message, $report_type, $category, $renderer) {
    $msg = new PHPMailer();
    if (FEATURE_EMAIL_TRACKING) {
        $msg->setFrom($mailname . '@' . MAIL_DOMAIN, $name . ' via Blocked.org.uk');
        $msg->Sender = $mailname.'@'.MAIL_DOMAIN;
    } else {
        $msg->AddReplyTo($email, $name);
        $msg->setFrom(SITE_EMAIL, $name . ' via Blocked.org.uk');
        $msg->Sender = SITE_EMAIL;
    }
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
            'report_type' => explode(",", $report_type),
            'category' => $category
            )
        );

    if(!$msg->send()) {
        error_log("Unable to send message: " . $msg->ErrorInfo);
        return false;
    }
    return true;
}

function sendUserVerification($email, $name, $token, $attempt, $renderer) {
    $msg = new PHPMailer();
    $msg->setFrom(SITE_EMAIL, SITE_NAME);
    $msg->addAddress(
        $email,
        $name
        );
    $msg->Subject = ($attempt > 1 ? 'Reminder: ' : '') . "Confirm your email address";
    $msg->isHTML(false);
    $msg->CharSet = "utf-8";
    $msg->Body = $renderer->render(
        'verify_email.txt',
        array(
            'name' => $name,
            'email' => $email,
            'confirm_url' => VERIFY_URL,
            'token' => $token,
            'site_url' => SITE_URL,
            'site_name' => SITE_NAME,
            'site_email' => SITE_EMAIL
        )
    );
    if (!$msg->Send()) {
        error_log("Unable to send message: " . $msg->ErrorInfo);
    }

}

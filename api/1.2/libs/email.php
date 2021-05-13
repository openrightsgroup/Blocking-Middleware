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
            'network' => $network,
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

function sendISPReminder($row, $renderer) {
    $msg = new PHPMailer();



}

function sendBBFCReport($mailname, $name, $email, $network, $original_network, $url, $message, $additional_contact, $renderer) {
    
    #    <option value="" selected="selected">- Select -</option>
    #    <option value="3">3</option>
    #    <option value="EE">EE</option>
    #    <option value="EE Strict">EE Strict (for EE customers under the age of 12)</option>
    #    <option value="O2">O2</option>
    #    <option value="Vodafone">Vodafone</option>
    #    <option value="Other">Other</option>

           
    //     $bbfcdata = array(
    //         "who_is_your_mobile_network_operator" => $original_network,
    //         "have_you_contacted_your_mobile_operator" => "01", # yes "01"
    //         "what_was_your_mobile_operators_response_to_your_complaint" => $previous, # multine
    //         "your_name" => $name,
    //         "your_email" => $mailname . '@' . MAIL_DOMAIN,
    //         "additional_contact_information" => $additional_contact,
    //         "url_of_the_content_in_question" => $url,
    //         "nature_of_the_complaint" => $message
    //         );

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
    $msg->Subject = "Request for BBFC review - " . $url;
    #$msg->addCustomHeader("Auto-Submitted", "auto-generated");
    $msg->isHTML(false);
    $msg->CharSet = 'utf-8';
    $msg->Body = $renderer->render(
        'report_bbfc.txt',
        array(
            'reporter_email' => (FEATURE_EMAIL_TRACKING) ? ($mailname . "@" . MAIL_DOMAIN) : $email ,
            'reporter_name' => $name,
            'url' => $url,
            'message' => $message,
            'additional_contact' => $additional_contact,
            'network' => $network,
            'original_network' => $original_network,
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

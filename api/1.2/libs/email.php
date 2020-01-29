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

function sendBBFCReport($mailname, $name, $email, $network, $url, $message, $previous, $additional_contact) {
    
    #    <option value="" selected="selected">- Select -</option>
    #    <option value="3">3</option>
    #    <option value="EE">EE</option>
    #    <option value="EE Strict">EE Strict (for EE customers under the age of 12)</option>
    #    <option value="O2">O2</option>
    #    <option value="Vodafone">Vodafone</option>
    #    <option value="Other">Other</option>
    
    if ($network == "Three") {
        $network = "3"; 
    }
    
    
    $req = new HTTP_Request2(BBFC_FORM_URL);
    
    $rsp = $req->send();
    
    $form_html = $rsp->getBody();
    
    preg_match('/form_build_id" value="(.*)"/', $form_html, $matches);
    $form_build_id = $matches[1];
    preg_match('/form_id" value="(.*)"/', $form_html, $matches);
    $form_id = $matches[1];
    
    // $form_build_id = 'ABCDEF';
    // $form_id = 'ABC';
           
    $data = array(
        "submitted[who_is_your_mobile_network_operator]" => $network,
        "submitted[please_specify]" => "",  # other ISP
        "submitted[have_you_contacted_your_mobile_operator]" => "01", # yes "01"
        "submitted[what_was_your_mobile_operators_response_to_your_complaint]" => $previous, # multine
        "submitted[your_name]" => $name,
        "submitted[your_email]" => $mailname . '@' . MAIL_DOMAIN,
        "submitted[additional_contact_information]" => $additional_contact,
        "submitted[url_of_the_content_in_question]" => $url,
        "submitted[nature_of_the_complaint]" => $message,
        # "details[sid]" 
        "details[page_num]" => "1",
        "details[page_count]" => "1",
        "details[finished]" => "0",

        "form_build_id" => $form_build_id,
        "form_id" => $form_id,
        "op" => "Submit"
        );
    foreach ($data as $k => $v) {
        error_log("BBFC submit data: $k = $v");
    }
    
    $req = new HTTP_Request2(BBFC_SUBMIT_URL);
    $rsp = $req->setMethod(HTTP_Request2::METHOD_POST)
        ->addPostParameter($data)
        ->send();
    $status = $rsp->getStatus();
    $body = $rsp->getBody();
    error_log("BBFC Submit $status $body");
        
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

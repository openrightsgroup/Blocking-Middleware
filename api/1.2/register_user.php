<?php
	include('libs/DB.php');
        include('libs/password.php');
        include('libs/compat.php');
        include('libs/pki.php');

        header('Content-type: application/json');
        header("API-Version: $APIVersion");


        $email = mysql_real_escape_string($_POST['email']);
        /* removed escape_string below, since if user's password contains single quote it will have hashed a 
           different value. */

        // only for PHP >= 5.5; otherwise uses pure-PHP version from libs/password.php
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
	$probeHMAC = md5($Salt . rand() . $email);

        $result = array();
        $result['success'] = false;

        if(empty($email) || empty($password))
        {
                $result['error'] = "Email address or password were blank";
                $status = 400;
        }
        else
        {
                $secret = Middleware::generateSharedSecret(); 
                $Query = "insert into users (email, password, probeHMAC, secret) VALUES (\"$email\",\"$password\",\"$probeHMAC\",\"$secret\")";
                mysql_query($Query);

                $mysql_errno = mysql_errno();
                if($mysql_errno == 0)
                {
                        $status = 201;
                        $result['success'] = true;
                        $result['status'] = 'pending';
                        $result['secret'] = $secret;
                }
                elseif ($mysql_errno == 1062) {
                        $result['error'] = "A user account with this email address has already been registered";
                        $status = 409;
                }
                else
                {
                        $result['error'] = mysql_error();
                        $status = 500;
                }
        }

        if ($status) {
                http_response_code($status);
        }
        print(json_encode($result));

<?php
	include('libs/DB.php');

        header('Content-type: application/json');
        header("API-Version: $APIVersion");


        $email = mysql_real_escape_string($_POST['email']);
        /* removed escape_string below, since if user's password contains single quote it will have hashed a 
           different value. */
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
	$probeHMAC = md5($Salt . rand() . $email);

        $result = array();
        $result['success'] = false;

        if(empty($email) || empty($password))
        {
                $result['error'] = "Email address or password were blank";
        }
        else
        {
                $Query = "insert into users (email, password, probeHMAC) VALUES (\"$email\",\"$password\",\"$probeHMAC\")";
                mysql_query($Query);

                if(mysql_errno() == 0)
                {
			include('libs/pki.php');
			$rowID = mysql_insert_id();
			$secret = Middleware::generateSharedSecret(); 


                        $Query = "update users set secret = \"" . $secret ."\" where id = " . $rowID;
                        mysql_query($Query);

                        if(mysql_errno() == 0)
                        {
                                $result['success'] = true;
                                $result['status'] = 'pending';
                                $result['secret'] = $secret;
                        }
                        else
                        {
                                $result['error'] = "Your user account was created but storing your shared secret failed. Please contact ORG";
                        }
                }
                else
                {
                        $result['error'] = mysql_error();
                }
        }


        print(json_encode($result));

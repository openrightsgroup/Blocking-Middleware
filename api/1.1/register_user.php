<?php
	include('libs/DB.php');

        header('Content-type: application/json');
        header("API-Version: $APIVersion");


        $email = mysql_real_escape_string($_POST['email']);
        $password = password_hash(mysql_real_escape_string($_POST['password']), PASSWORD_DEFAULT);
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
			$pki = Middleware::generateKeys(); 


			if(!empty($pki['public']))
			{
				$Query = "update users set publicKey = \"" . $pki['public'] ."\" where id = " . $rowID;
		                mysql_query($Query);

				if(mysql_errno() == 0)
		                {
                	        	$result['success'] = true;
					$result['status'] = 'pending';
					$result['private_key'] = $pki['private'];
				}
				else
				{
					$result['error'] = "Your user account was created but building a public/private key failed. Please contact ORG";
				}
			}
			else
			{
				$result['error'] = "Your user account was created but building a public/private key failed. Please contact ORG";
			}
                }
                else
                {
                        $result['error'] = mysql_error();
                }
        }


        print(json_encode($result));

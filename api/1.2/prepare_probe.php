<?php
	include('libs/DB.php');
        include('libs/password.php');
        include('libs/compat.php');
        include('libs/pki.php');

        header('Content-type: application/json');
        header("API-Version: $APIVersion");


        $email = mysql_real_escape_string($_POST['email']);
	$signature = $_POST['signature']; // sha512 hmac should not need to have been base64-encoded

        $result = array();
        $result['success'] = false;
	
	if(empty($email) || empty($signature))
        {
                $result['error'] = "Email address or signature were blank";
                $status = 400;
        }
        else
        {
                $Query = "select secret,status from users where email = \"$email\"";
		$mySQLresult = mysql_query($Query);

                if(mysql_errno() == 0)
                {
			if(mysql_num_rows($mySQLresult) == 1)
			{
				$row = mysql_fetch_assoc($mySQLresult);

				if($row['status'] == "ok")
				{
					if(Middleware::verifyUserMessage($email, $row['secret'],$signature))
					{
						// Using 32 bytes for randomness as it seems secure enough.
						$probeHMAC = password_hash(date('Y-m-d H:i:s') . openssl_random_pseudo_bytes(32, $crypto_strong));

						if($crypto_strong)
						{
							$result['success'] = true;

							$Query = "update users set probeHMAC = \"$probeHMAC\" where email = \"$email\"";
							mysql_query($Query);

							$result['probe_hmac'] = $probeHMAC;
                                                        $status = 200;
						}
						else
						{
							$result['error'] = "Failed to generate a secure signature";
                                                        $status = 500;
						}
					}
					else
					{
						$result['error'] = "Public key signature verification failed";
                                                $status = 403;
					}
				}
				else
				{
					$result['error'] = "Account is " . $row['status'];
                                        $status = 403;
				}
			}
			else
			{
				$result['error'] = "No matches in DB. Please contact ORG support";
                                $status = 404;
			}
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

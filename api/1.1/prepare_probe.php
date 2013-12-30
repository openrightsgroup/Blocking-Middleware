<?php
	include('libs/DB.php');

        header('Content-type: application/json');
        header("API-Version: $APIVersion");


        $email = mysql_real_escape_string($_POST['email']);
	$signature = base64_decode(str_replace(" ","",$_POST['signature']));

        $result = array();
        $result['success'] = false;
	
	if(empty($email) || empty($signature))
        {
                $result['error'] = "Email address or signature were blank";
        }
        else
        {
                $Query = "select publicKey,status from users where email = \"$email\"";
		$mySQLresult = mysql_query($Query);

                if(mysql_errno() == 0)
                {
			if(mysql_num_rows($mySQLresult) == 1)
			{
				include('libs/pki.php');

				$row = mysql_fetch_assoc($mySQLresult);

				if($row['status'] == "ok")
				{
					if(Middleware::verifyUserSignature($row['publicKey'],$signature,$email))
					{
						// Using 32 bytes for randomness as it seems secure enough.
						$probeHMAC = password_hash(date('Y-m-d H:i:s') . openssl_random_pseudo_bytes(32, $crypto_strong));

						if($crypto_strong)
						{
							$result['success'] = true;

							$Query = "update users set probeHMAC = \"$probeHMAC\" where email = \"$email\"";
							mysql_query($Query);

							$result['probe_hmac'] = $probeHMAC;
						}
						else
						{
							$result['error'] = "Failed to generate a secure signature";
						}
					}
					else
					{
						$result['error'] = "Public key signature verification failed";
					}
				}
				else
				{
					$result['error'] = "Account is " . $row['status'];
				}
			}
			else
			{
				$result['error'] = "No matches in DB. Please contact ORG support";
			}
                }
                else
                {
                        $result['error'] = mysql_error();
                }
        }


        print(json_encode($result));

<?php
	include('libs/DB.php');
        include('libs/password.php');
        include('libs/compat.php');
        include('libs/pki.php');

        header('Content-type: application/json');
        header("API-Version: $APIVersion");


        $email = mysql_real_escape_string($_POST['email']);
        $probeUUID = mysql_real_escape_string($_POST['probe_uuid']);
	$probeSeed = mysql_real_escape_string($_POST['probe_seed']);
	$probeType = mysql_real_escape_string($_POST['probe_type']);
	$signature = $_POST['signature'];
	$countryCode = mysql_real_escape_string($_POST['cc']);

        $result = array();
        $result['success'] = false;
	
	if(empty($email) || empty($signature))
        {
                $result['error'] = "Email address or signature were blank";
                $status = 400;
        }
        else
        {
                $Query = "select id,secret,probeHMAC,status from users where email = \"$email\"";
		$mySQLresult = mysql_query($Query);

                if(mysql_errno() == 0)
                {
			if(mysql_num_rows($mySQLresult) == 1)
			{
				$row = mysql_fetch_assoc($mySQLresult);

				if($row['status'] == "ok")
				{
					if(md5($probeSeed . "-" . $row['probeHMAC']) == $probeUUID)
					{
						if(Middleware::verifyUserMessage($probeUUID, $row['secret'],$signature))
						{
							$secret = Middleware::generateSharedSecret();

                                                        $userID = $row['id'];

                                                        $Query = "insert into probes (uuid,userID,secret,countryCode,type) VALUES (\"$probeUUID\",$userID,\"$secret\",\"$countryCode\",\"$probeType\")";
                                                        $mySQLresult = mysql_query($Query);
                                                        if(mysql_errno() == 0)
                                                        {
                                                                /*print($pki['private']);
                                                                print("\n\n\n\n\n");*/
                                                                $result['success'] = true;
                                                                $result['secret'] = $secret;
                                                                $status = 201;
                                                        }
                                                        else
                                                        {
                                                                $result['error'] = "Adding probe to the database failed";
                                                                $status = 500;
                                                        }
						}
						else
						{
							$result['error'] = "Signature verification failed";
                                                        $status = 403;
						}
					}
					else
					{
						$result['error'] = "Probe seed and HMAC verification failed";
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

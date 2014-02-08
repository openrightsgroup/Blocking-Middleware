<?php
	include('libs/DB.php');

        header('Content-type: application/json');
        header("API-Version: $APIVersion");


        $email = mysql_real_escape_string($_POST['email']);
        $probeUUID = mysql_real_escape_string($_POST['probe_uuid']);
	$probeSeed = mysql_real_escape_string($_POST['probe_seed']);
	$probeType = mysql_real_escape_string($_POST['probe_type']);
	$signature = base64_decode(str_replace(" ","",$_POST['signature']));
	$countryCode = mysql_real_escape_string($_POST['cc']);

        $result = array();
        $result['success'] = false;
	
	if(empty($email) || empty($signature))
        {
                $result['error'] = "Email address or signature were blank";
        }
        else
        {
                $Query = "select id,publicKey,probeHMAC,status from users where email = \"$email\"";
		$mySQLresult = mysql_query($Query);

                if(mysql_errno() == 0)
                {
			if(mysql_num_rows($mySQLresult) == 1)
			{
				include('libs/pki.php');

				$row = mysql_fetch_assoc($mySQLresult);

				if($row['status'] == "ok")
				{
					if(md5($probeSeed . "-" . $row['probeHMAC']) == $probeUUID)
					{
						if(Middleware::verifyUserSignature($row['publicKey'],$signature,$probeUUID))
						{
							$pki = Middleware::generateKeys();

							if(!empty($pki['public']))
							{
								$userID = $row['id'];

								$Query = "insert into probes (uuid,userID,publicKey,countryCode,type) VALUES (\"$probeUUID\",$userID,\"{$pki['public']}\",\"$countryCode\",\"$probeType\")";
								$mySQLresult = mysql_query($Query);
								if(mysql_errno() == 0)
								{
									/*print($pki['private']);
									print("\n\n\n\n\n");*/
									$result['success'] = true;
									$result['private_key'] = $pki['private'];
								}
								else
								{
									$result['error'] = "Adding probe to the database failed";
								}
							}
							else
							{
								$result['error'] = "Succesfully authenticated but probe PKI generation failed";
							}
						}
						else
						{
							$result['error'] = "Public key signature verification failed";
						}
					}
					else
					{
						$result['error'] = "Probe seed and HMAC verification failed";
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

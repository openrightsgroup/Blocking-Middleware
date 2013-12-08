<?php
	header('Content-type: application/json');
	header('API-Version: 1');
	
	include('libs/DB.php');
	
	$gcmID = mysql_real_escape_string($_POST['gcm_id']);
	$probeUUID = mysql_real_escape_string($_POST['probe_uuid']);
	$frequency = (int) mysql_real_escape_string($_POST['frequency']);
	$gcmType = (int) mysql_real_escape_string($_POST['gcm_type']);
	$signature = base64_decode(str_replace(" ","",$_POST['signature']));
	
	$result = array();
	$result['success'] = false;

	$date = date('Y-m-d H:i:s');
	
	if(empty($gcmID) || empty($probeUUID) || empty($signature))
	{
		$result['error'] = "GCM registration ID, device ID or signature were blank";
	}
	else
	{	
		$Query = "select publicKey from probes where uuid = \"$probeUUID\"";
                $mySQLresult = mysql_query($Query);

                if(mysql_errno() == 0)
                {
                        if(mysql_num_rows($mySQLresult) == 1)
                        {
                                include('libs/pki.php');

                                $row = mysql_fetch_assoc($mySQLresult);

				if(Middleware::verifyUserSignature($row['publicKey'],$signature,$gcmID))
                                {
					$Query = "UPDATE probes set gcmRegID = \"$gcmID\", lastSeen = \"$date\", gcmType = $gcmType, frequency = $frequency where uuid = \"$probeUUID\"";
					mysql_query($Query);
	
					if(mysql_errno() == 0)
					{
						$result['success'] = true;
					}
					else
					{
						$result['error'] = mysql_error();
					}
				}
				else
				{
					$result['error'] = "Public key signature verification failed";
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

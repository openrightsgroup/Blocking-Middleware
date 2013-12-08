<?php
	include('libs/DB.php');

        header('Content-type: application/json');
        header("API-Version: $APIVersion");


        $email = mysql_real_escape_string($_POST['email']);
	$signature = base64_decode(str_replace(" ","",$_POST['signature']));
	$url = mysql_real_escape_string($_POST['url']);

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

				if(Middleware::verifyUserSignature($row['publicKey'],$signature,$url))
				{
					$Query = "insert into tempURLs (URL,hash,lastPolled) VALUES (\"$url\",\"$md5\",\"2013-12-01 00:00:01\")";
        	                        mysql_query($Query);
	
                	                if(mysql_errno() == 0)
                        	        {
                                	        $result['success'] = true;
                                        	$result['uuid'] = mysql_insert_id();
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

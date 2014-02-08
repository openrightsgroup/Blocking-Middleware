<?php
	include('libs/DB.php');
        include('libs/password.php');
        include('libs/compat.php');
        include('libs/pki.php');

        header('Content-type: application/json');
        header("API-Version: $APIVersion");

        // I'm leaning towards this being a GET endpoint, since it doesn't alter any data
        // changing to $_REQUEST in the meantime
        $email = mysql_real_escape_string($_REQUEST['email']);
	$signature = $_REQUEST['signature'];

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

				if(Middleware::verifyUserMessage($email,$row['secret'],$signature))
				{
					$result['success'] = true;
					$result['status'] = $row['status'];
				}
				else
				{
					$result['error'] = "Signature verification failed";
                                        $status = 403;
				}
			}
			else
			{
                                // will error if there is a duplicate email address
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

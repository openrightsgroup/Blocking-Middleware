<?php
	/*
	* Copyright (C) 2013 - Gareth Llewellyn
	*
	* This file is part of the Open Rights Group Blocking Middleware
	* https://github.com/openrightsgroup/Blocking-Middleware
	*
	* This program is free software: you can redistribute it and/or modify it
	* under the terms of the GNU General Public License as published by
	* the Free Software Foundation, either version 3 of the License, or
	* (at your option) any later version.
	*
	* This program is distributed in the hope that it will be useful, but WITHOUT
	* ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
	* FOR A PARTICULAR PURPOSE. See the GNU General Public License
	* for more details.
	*
	* You should have received a copy of the GNU General Public License along with
	* this program. If not, see <http://www.gnu.org/licenses/>
	*/

	include('libs/DB.php');

        header('Content-type: application/json');
        header("API-Version: $APIVersion");


        $email = mysql_real_escape_string($_POST['email']);
        $probeUUID = mysql_real_escape_string($_POST['probe_uuid']);
	$probeSeed = mysql_real_escape_string($_POST['probe_seed']);
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

								$Query = "insert into probes (uuid,userID,publicKey,countryCode) VALUES (\"$probeUUID\",$userID,\"{$pki['public']}\",\"$countryCode\")";
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

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

	header('Content-type: application/json');
	header('API-Version: 1');
	
	include('libs/DB.php');
	
	$gcmID = mysql_real_escape_string($_POST['gcmid']);
	$probeUUID = mysql_real_escape_string($_POST['probe_uuid']);
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
					$Query = "UPDATE probes set gcmRegID = \"$gcmID\", lastSeen = \"$date\", enabled = 1 where uuid = \"$probeUUID\"";
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

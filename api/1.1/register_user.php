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
        $password = md5($Salt . mysql_real_escape_string($_POST['password']));
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

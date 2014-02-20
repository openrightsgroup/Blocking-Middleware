<?php
        include('libs/DB.php');
        include_once 'libs/GCM.php';

        date_default_timezone_set('Europe/London');
        $date = date('Y-m-d H:i:s');

	$URLQuery = "select tempID,URL,hash from tempURLs ORDER BY lastPolled ASC,polledAttempts DESC LIMIT 1";
        $resultMySQL = mysql_query($URLQuery);

	$result = array();
        $result['success'] = true;

        if (mysql_num_rows($resultMySQL) == 0)
        {
                $result['error'] = "No rows found, nothing to print so am exiting";
        }
	else
	{
        	$row = mysql_fetch_assoc($resultMySQL);
        
        	$result['success'] = true;

		$result['url'] = $row['URL'];
		$result['hash'] = $row['hash'];
		
		$SuccessQuery = "update tempURLs set lastPolled = \"".date('Y-m-d H:i:s')."\", polledAttempts = polledAttempts + 1 where tempID = " . $row['tempID'];
                mysql_query($SuccessQuery);
		print(mysql_error());
	}
	print(json_encode($result));

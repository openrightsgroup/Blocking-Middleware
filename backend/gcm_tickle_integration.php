<?php
	/*--------------------------------------------------------------------------
	
	This file needs to be cron'd once a minute - it will do a SQL query for
	probes set to be 'prompted' to download data so the URL itself won't be
	sent "over the air"

	--------------------------------------------------------------------------*/
	$dir = dirname(__FILE__);
	include "$dir/../api/1.2/libs/DB.php";
	include "$dir/../api/1.2/libs/GCM.php";

    //include('/HighIO/www/domains/bowdlerize/api/1.2/libs/DB.php');
    //include('/HighIO/www/domains/bowdlerize/api/1.2/libs/GCM.php');

	$conn = new APIDB($dbhost,$dbuser,$dbpass,$dbname);
	date_default_timezone_set('Europe/London');
        $date = date('Y-m-d H:i:s');
        $gcm = new GCM();
        $GCMIDs = array();

	$Debug = true;

	//-------------------------------------------------------
	// Probes

    //Get Tickle Only
    //$QueryString = "SELECT * FROM probes where gcmRegID IS NOT null AND gcmRegID <> '' AND secret IS NOT null AND gcmType = 1";

    //Gets Tickle & Full GCM (Full GCM will resepct the tickle and avoids the issues about ISP queues)
    $QueryString = "SELECT * FROM probes where gcmRegID IS NOT null AND gcmRegID <> '' AND secret IS NOT null AND gcmType in (0,1)";

	$rs = $conn->query($QueryString,array());

	while ($row = $rs->fetch_assoc())
        {
		$lastPolled  = strtotime($row['lastSeen']);
                $now = strtotime($date);
                $differenceInSeconds = $now - $lastPolled;

                if($differenceInSeconds > ($row['frequency'] * 60))
                {
                        if($Debug)
                                print("Found a probe last seen $differenceInSeconds seconds ago with a requested frequency of " . ($row['frequency'] * 60) . " - We're good to go [ADDING {$row['id']} TO POOL]\n");
		
                        $GCMIDs[] = $row['gcmRegID'];

			$conn->query("update probes set probeReqSent=probeReqSent+1 where uuid=?",
                        array($row['uuid'])
                        );
			
                }
                else
                {
                        if($Debug)
                                print("Found a probe last seen $differenceInSeconds seconds ago with a requested frequency of " . ($row['frequency'] * 60) . " [NOT SENDING TO {$row['id']}]\n");
                }
        }



	//-------------------------------------------------
	// Putting it all together
        if(!empty($GCMIDs))
        {
        	//Prepare the message to be sent to GCM recipiants
                $message = array('tickle' => true,
                                "urgency" => 0,
                                );

                $result = $gcm->send_notification($GCMIDs, $message, 60, false);
                $json = json_decode($result,true);
			

                //if($json['failure'] == 0)
		//Ideally we should go through each "failed" object and if for example it's an invalid regID we should remove it
		print_r($json);
                if(true)
                {
                 	if($Debug)
                        	print("Successful GCM sent\n");

          	}
                else
                {
                	if($Debug)
                        	print("Failed GCM message ( ".$result." )\n");

               	}
	}


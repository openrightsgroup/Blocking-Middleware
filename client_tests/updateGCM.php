<?php

$priv_key = "-----BEGIN RSA PRIVATE KEY-----

PRIVATE KEY FROM PROBE REGISTRATION

-----END RSA PRIVATE KEY-----
";

	$Email = "EMAIL ADDRESS";

	//Unique to this user
	$probeHMAC = "HMAC_CODE";

	//Should be unique to this device (the Android ID or Raspberry Pi MAC)
	$probeSeed = "PROBE_SEED";

	$probeUUID = md5($probeSeed . "-" . $probeHMAC);

	$GCMID = "GOOGLE_CLOUD_REGISTRATION_ID";

	//Generate the signature
	openssl_sign($GCMID, $signature, $priv_key,OPENSSL_ALGO_SHA1);

	$POST_Data = "gcmid=$GCMID&signature=".urlencode(base64_encode($signature))."&probe_uuid=$probeUUID";
        
	$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://bowdlerize.co.uk/api/1.1/update/gcm');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $POST_Data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
	print($result);

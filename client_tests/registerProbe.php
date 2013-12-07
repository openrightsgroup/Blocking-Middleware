<?php

$priv_key = "-----BEGIN RSA PRIVATE KEY-----

PRIVATE KEY FROM USER REGISTRATION

-----END RSA PRIVATE KEY-----
";

	$Email = "EMAIL ADDRESS";

	//Unique to this user
	$probeHMAC = "HMAC_CODE";

	//Should be unique to this device (the Android ID or Raspberry Pi MAC)
	$probeSeed = "PROBE_SEED";

	$probeUUID = md5($probeSeed . "-" . $probeHMAC);

	//Generate the signature
	openssl_sign($probeUUID, $signature, $priv_key,OPENSSL_ALGO_SHA1);

	$POST_Data = "email=$Email&signature=".urlencode(base64_encode($signature))."&probe_seed=$probeSeed&probe_uuid=$probeUUID&cc=uk&type=android";
        
	$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://bowdlerize.co.uk/api/1.1/register/probe');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $POST_Data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
	print($result);

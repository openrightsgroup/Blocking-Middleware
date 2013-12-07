<?php

$priv_key = "-----BEGIN RSA PRIVATE KEY-----

PRIVATE KEY FROM USER REGISTRATION

-----END RSA PRIVATE KEY-----
";

	$Email = "EMAIL ADDRESS";
	$Password = "PASSWORD";

	// compute signature
	openssl_sign($Email, $signature, $priv_key,OPENSSL_ALGO_SHA1);


	$POST_Data = "email=$Email&password=$Password&signature=".urlencode(base64_encode($signature));
        
	$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://bowdlerize.co.uk/api/1.1/status/user');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $POST_Data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
	print($result);



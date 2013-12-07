<?php

class Middleware
{
	public static function generateKeys()
	{
		$dn = array("countryName" => 'XX', "stateOrProvinceName" => 'State', "localityName" => 'SomewhereCity', "organizationName" =>'MySelf', "organizationalUnitName" => 'Whatever', "commonName" => 'mySelf', "emailAddress" => 'user@example.com');

                $numberofdays = 3650;

                //RSA encryption and 2048 bits length
                $privkey = openssl_pkey_new(array('private_key_bits' => 2048,'private_key_type' => OPENSSL_KEYTYPE_RSA));
                $csr = openssl_csr_new($dn, $privkey);
                $sscert = openssl_csr_sign($csr, null, $privkey, $numberofdays);
                openssl_x509_export($sscert, $publickey);
                openssl_pkey_export($privkey, $privatekey);
                openssl_csr_export($csr, $csrStr);

		return array('public' => $publickey, 'private' => $privatekey); 
	}

	public static function verifyUserSignature($pubKey, $signature, $data)
	{
		$ok = openssl_verify($data, $signature, $pubKey,OPENSSL_ALGO_SHA1);

                openssl_free_key($pubkeyid);

		if ($ok == 1) 
		{
			return true;
		} 
		else 
		{
			return false;	
		}
	}
}

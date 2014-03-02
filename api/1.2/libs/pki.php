<?php

include_once "exceptions.php";

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

	public static function generateSharedSecret($length=36)
	{
		// generates a suitably long shared secret to use with HMAC signing
		$chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$char_count = 62;

		$out = '';
		for ($i=0; $i < $length; $i++) {
			$out .= substr($chars, rand(0, $char_count-1), 1);
		}
		return $out;
	}

	public static function createSignatureHash($message, $secret) {
		return hash_hmac('sha512', $message, $secret);
	}

	public static function verifyUserMessage($message, $secret, $hash) {
		if (hash_hmac('sha512', $message, $secret) == $hash) {
			return true;
		} else {
			throw new SignatureError();
		}
	}
		
        public static function checkMessageTimestamp($time) {
                # messages are valid for up to 15 minutes
                $now = time();
                $msgtime = strtotime($time);
                if (abs($msgtime - $now) > (15*60)) {
                        throw new TimestampError();
                } else {
                        return true;
                }
        }

}

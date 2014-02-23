<?php

require_once __DIR__."/../silex/vendor/autoload.php";

include_once "libs/DB.php";
include_once "libs/pki.php";
include_once "libs/password.php";
include_once "libs/exceptions.php";
include_once "libs/services.php";

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

$app = new Silex\Application();
$app['debug'] = true;

$app['service.db'] = $app->share(function() {
	global $dbuser, $dbpass, $dbhost, $dbname;
	return new APIDB($dbhost, $dbuser, $dbpass, $dbname);
});

$app['db.user.load'] = function($app) {
	return new UserLoader($app['service.db']);
};
$app['db.probe.load'] = function($app) {
	return new ProbeLoader($app['service.db']);
};

function checkParameters($req, $params) {
	foreach($params as $requiredParam) {
		if (!$req->get($requiredParam)) {
			throw new InputError();
		}
	}
}

$app->error(function(APIException $e, $code) {
	switch(get_class($e)) {
		case "ProbeLookupError":
			$code = 404;
			$message = "No matches in DB, please contact ORG support";
			break;
		case "UserLookupError":
			$code = 404;
			$message = "No matches in DB, please contact ORG support";
			break;
		case "InputError":
			$code = 400;
			$message = "One or more required parameters missing or invalid";
			break;
		case "DatabaseError":
			$code = 500;
			$message = "A database error occurred: " . $e->getMessage();
			break;
		case "SignatureError":
			$code = 403;
			$message = "Signature verification failed.";
			break;
		case "TimestampError":
			$code = 400;
			$message = "Timestamp out of range (too old/new)";
			break;
		case "ConflictError":
			$code = 409;
			$message = $e->getMessage();
			break;
		case 'UserStatusError':
			$code = 403;
			$message = "Account is " . $e->getMessage();
			break;
	};
	return new JsonResponse(array('success'=>false, 'error'=>$message), $code);
});

$app->after(function(Request $request, Response $response) {
	global $APIVersion; // from DB.php
	$response->headers->set('API-Version', $APIVersion);
});

/* URL Endpoints */

$app->post('/submit/url', function(Request $req) use ($app) {
	/* Add a URL for testing */
	$conn = $app['service.db'];

	checkParameters($req, array('email','signature'));

	$row = $app['db.user.load']->load($req->get('email'));

	Middleware::verifyUserMessage($req->get('url'), $row['secret'], $req->get('signature'));

	$conn->query(
		"insert into tempURLs(URL, hash, lastPolled) values (?,?,now())",
		array($req->get('url'), md5($req->get('url')))
		);


	return $app->json(array('success' => true, 'uuid' => $conn->insert_id), 201);
});
	
$app->get('/status/user',function(Request $req) use ($app) {
	$conn = $app['service.db'];

	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$row = $app['db.user.load']->load($req->get('email'));

	Middleware::verifyUserMessage( $req->get('email') .':'. $req->get('date'), 
		$row['secret'], $req->get('signature'));


	return $app->json(array('success'=>'true', 'status'=> $row['status']));
	
});


$app->post('/register/user', function(Request $req) use ($app) {
	global $Salt;

	$conn = $app['service.db'];
	checkParameters($req, array('email','password'));

	$email = $req->get('email');
	$password = password_hash($req->get('password'), PASSWORD_DEFAULT);
	$probeHMAC = md5($Salt . rand() . $email);

	$secret = Middleware::generateSharedSecret(); 
	try {
		$result = $conn->query(
			"insert into users (email, password, probeHMAC, secret) VALUES (?,?,?,?)",
			array($email,$password,$probeHMAC,$secret)
			);
	}
	catch (DatabaseError $e) {
		
		if ($e->getCode() == 1062) {
			throw new ConflictError("A user account with this email address has already been registered");
		} else {
			throw $e;
		}
	}
	return $app->json(array(
		'success'=>true,
		'status'=>'pending',
		'secret'=>$secret
		),
		201
	);
});

$app->post('/prepare/probe', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$conn = $app['service.db'];

	$row = $app['db.user.load']->load($req->get('email'));
	if ($row['status'] != 'ok') {
		throw new UserStatusError($row['status']);
	}

	Middleware::verifyUserMessage($req->get('email') . ':' . $req->get('date'), $row['secret'], $req->get('signature'));

	$probeHMAC = Middleware::generateSharedSecret(32);

	$conn->query("update users set probeHMAC = ? where email = ?",
		array($probeHMAC, $req->get('email'))
		);

	return $app->json(array(
		'success' => true,
		'probe_hmac' => $probeHMAC
		));
});

$app->post('/register/probe', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature'));

	$conn = $app['service.db'];
	$row = $app['db.user.load']->load($req->get('email'));
	if ($row['status'] != 'ok') {
		throw new UserStatusError($row['status']);
	}

	$check_uuid = md5($req->get('probe_seed') . '-' . $row['probeHMAC']);
	if ($check_uuid != $req->get('probe_uuid')) {
		return $app->json(array(
			'success' => false,
			'error' => 'Probe seed and HMAC verification failed'
			), 403);
	}

	$secret = Middleware::generateSharedSecret();

	$conn->query("insert into probes (uuid,userID,secret,countrycode,type) values (?,?,?,?,?)",
		array($req->get('probe_uuid'), $row['id'], $secret, $req->get('country_code'), $req->get('probe_type'))
		);

	return $app->json(array(
		'success' => true,
		'secret' => $secret), 201);
});

$app->get('/request/httpt', function(Request $req) use ($app) {
	checkParameters($req, array('probe_uuid','signature'));
	$conn = $app['service.db'];

	$row = $app['db.probe.load']->load($req->get('probe_uuid'));

	Middleware::verifyUserMessage($req->get('probe_uuid'), $req->get('signature'), $row['secret']);

	$result = $conn->query("select tempID,URL,hash from tempURLs ORDER BY lastPolled ASC,polledAttempts DESC LIMIT 1");
	if ($result->num_rows == 0) {
		return $app->json(array(
			'success' => false,
			'error' => 'No queued URLs found'
			), 404);
	}
	$row = $result->fetch_assoc();
	$ret = array(
		'success' => true,
		'url' => $row['URL'],
		'hash' => $row['hash']
		);
	$conn->query(
		"update tempURLs set lastPolled = now(), polledAttempts = polledAttempts + 1 where tempID = ?",
		array($row['tempID'])
		);

	return $app->json($ret, 200);
});

$app->post('/response/httpt', function(Request $req) use ($app) {

	return $app->json(array('success' => true));

});

$app->get('/config/{version}', function (Request $req, $version) use ($app) {
	if (!$version) {
		throw new InputError();
	}
	if ($version != 'latest' && !is_numeric($version)) {
		throw new InputError();
	}
		
	// fetch and return config here
	
	return "";
});

$app->run();

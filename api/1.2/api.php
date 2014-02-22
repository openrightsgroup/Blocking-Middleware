<?php

require_once __DIR__."/../silex/vendor/autoload.php";

include "libs/DB.php";
include "libs/pki.php";
include "libs/password.php";
include "libs/exceptions.php";

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

$app = new Silex\Application();
$app['debug'] = true;

$app['service.db'] = function() {
	global $dbuser, $dbpass, $dbhost, $dbname;
	return new APIDB($dbhost, $dbuser, $dbpass, $dbname);
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
		case "UserLookupError":
			$code = 404;
			$message = "No matches in DB, please contact ORG support";
			break;
		case "InputError":
			$code = 400;
			$message = "One or more required parameters missing";
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
	};
	return new JsonResponse(array('success'=>false, 'error'=>$message), $code);
});

$app->after(function(Request $request, Response $response) {
	global $APIVersion; // from DB.php
	$response->headers->set('API-Version', $APIVersion);
});

$app->post('/submit/url', function(Request $req) use ($app) {
	$conn = $app['service.db'];

	checkParameters($req, array('email','signature'));

	if (!$result = $conn->query(
		"select secret,status from users where email = ?",
		array($req->get('email'))
		)) {

		throw new DatabaseError($conn->error, $conn->errno);
	}
	if ($result->num_rows == 0) {
		throw new UserLookupError();
	}
	$row = $result->fetch_assoc();

	if (!Middleware::verifyUserMessage($req->get('url'), $row['secret'], $req->get('signature'))) {
		throw new SignatureError();
	}

	$result = $conn->query(
		"insert into tempURLs(URL, hash, lastPolled) values (?,?,now())",
		array($req->get('url'), md5($req->get('url')))
		);

	if (!$result) {
		throw new DatabaseError($conn->error, $conn->errno);
	}

	return $app->json(array('success' => true, 'uuid' => $conn->insert_id), 201);
});
	
$app->get('/status/user',function(Request $req) use ($app) {
	$conn = $app['service.db'];

	checkParameters($req, array('email','signature','date'));

	if (!Middleware::checkMessageTimestamp($req->get('date'))) {
		throw new TimestampError();
	}
	$result = $conn->query(
		"select secret,status from users where email = ?",
		array($req->get('email'))
		);

	if ($conn->errno != 0) {
		throw new DatabaseError($conn->error, $conn->errno);
	}
	if ($result->num_rows == 0) {
		throw new UserLookupError();
	}
	$row = $result->fetch_assoc();

	if (!Middleware::verifyUserMessage( $req->get('email') .':'. $req->get('date'), 
		$row['secret'], $req->get('signature'))) {

		throw new SignatureError();
	}

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
	$result = $conn->query(
		"insert into users (email, password, probeHMAC, secret) VALUES (?,?,?,?)",
		array($email,$password,$probeHMAC,$secret)
		);
	if (!$result) {
		if ($conn->errno == 1062) {
			throw new ConflictError("A user account with this email address has already been registered");
		} else {
			throw new DatabaseError($conn->error, $conn->errno);
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


$app->run();

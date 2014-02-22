<?php

require_once __DIR__."/../silex/vendor/autoload.php";

include "libs/DB.php";
include "libs/pki.php";

use Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();
$app['debug'] = true;

$app['db.service'] = function() {
	global $dbuser, $dbpass, $dbhost, $dbname;
	return new mysqli($dbhost, $dbuser, $dbpass, $dbname);
};

function error_response($app, $code, $message) {
	return $app->json(array(
		'success' => false,
		'error' => $message
		), $code);
}

$app->get('/hello/{name}', function ($name) use ($app) {
	return 'Hello ' . $app->escape($name);
});

$app->get('/submit/url', function(Request $req) use ($app) {
	$conn = $app['db.service'];

	if (!($req->get('email')) || !($req->get('signature'))) {
		return error_response($app, 400, 'Email address or signature was blank');
	}

	if (!$result = $conn->query(db_escape($conn,
		"select secret,status from users where email = ?",
		array($req->get('email'))
		))) {

		return error_response($app, 500, $conn->error);
	}
	if ($result->num_rows == 0) {
		return error_response($app, 404, 'No matches in DB, please contact ORG support');
	}
	$row = $result->fetch_assoc();

	if (!Middleware::verifyUserMessage($req->get('url'), $row['secret'], $req->get('signature'))) {
		return error_response($app, 403, 'Signature verification failed');
	}

	$result = $conn->query(db_escape($conn,
		"insert into tempURLs(URL, hash, lastPolled) values (?,?,now())"
		array($req->get('url'), md5($req->get('url')))
		));

	if (!$result) {
		return error_response($app, 500, $conn->error);
	}

	return $app->json(array('success' => true, 'uuid' => $conn->insert_id), 201);
});
	

$app->run();

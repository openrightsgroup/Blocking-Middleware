<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;


#--------- Begin  Administrator Functions

$app->get('/list/users/{status}', function (Request $req, $status) use ($app) {
	checkParameters($req, array('email','date','signature'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$user = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $user['secret'], $req->get('signature'));
	checkAdministrator($user);

	$conn = $app['service.db'];

	# if status has been supplied, only list users with that status
	if ($status) {
		$rs = $conn->query("select email, fullName, createdAt, status from users where status = ?",
			array($status));
	} else {
		$rs = $conn->query("select email, fullName, createdAt, status from users");
	}

	$out = array();
    foreach($rs as $row) {
		$out[] = $row;
	}

	return $app->json(array("success"=>true,"users"=>$out));
})
->value('status','');

$app->post('/status/user/{user}', function (Request $req, $user) use ($app) {
	# Set the status of a user
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));

	Middleware::verifyUserMessage($user . ":". $req->get('status'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);

	$conn = $app['service.db'];
	$result = $conn->query("UPDATE users set status = ? where email = ?",
		array($req->get('status'), $user));

	if ($result->rowCount() == 0) {
		throw new UserLookupError();
	}

	return $app->json(array('success' => true, "status" => $req->get('status'), "email" => $user));
});

$app->post('/status/probe/{uuid}', function (Request $req, $uuid) use ($app) {
	# Set the status of a probe
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($uuid . ":". $req->get('status'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);

	if (!($req->get('status') == "enabled" || $req->get('status') == 'disabled')) {
		return $app->json(array(
			"success"=> false,
			"error"=> "Unknown status: " . $req->get('status')
		), 500);
	}

	$conn = $app['service.db'];
	$result = $conn->query("UPDATE probes set enabled = ? where uuid = ?",
		array($req->get('status') == "enabled" ? 1 : 0, $uuid));

	if ($result->rowCount() == 0) {
		throw new ProbeLookupError();
	}

	return $app->json(array('success'=> true, "status"=> $req->get('status'), "email"=> $user));
});

# add a blacklist entry
$app->post('/ispreport/blacklist', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date','domain'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date') . ":". $req->get('domain'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);

    $loader = $app['db.blacklist.load'];

    $loader->insert($req->get('domain'));

    return $app->json(array('success' => true, 'domain' => $req->get('domain')));

});

$app->get('/ispreport/blacklist', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);
    
    $loader = $app['db.blacklist.load'];
    $entries = $loader->select();

    return $app->json(array('success' => true, 'blacklist' => $entries));

});

$app->delete('/ispreport/blacklist', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date','domain'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date') .":". $req->get('domain'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);

    $loader = $app['db.blacklist.load'];
    $loader->delete($req->get('domain'));

    return $app->json(array('success' => true, 'domain'=> $req->get('domain')));

});

/*  -------^---^---^---- End Administrator functions ... */


<?php

require_once __DIR__."/../../backend/silex/vendor/autoload.php";

include_once "libs/DB.php";
include_once "libs/amqp.php";
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

$app['service.amqp'] = $app->share(function() {
	return amqp_connect();
});

$app['db.user.load'] = function($app) {
	return new UserLoader($app['service.db']);
};
$app['db.probe.load'] = function($app) {
	return new ProbeLoader($app['service.db']);
};
$app['db.url.load'] = function($app) {
	return new UrlLoader($app['service.db']);
};
$app['db.isp.load'] = function($app) {
	return new IspLoader($app['service.db']);
};
$app['service.ip.query'] = function($app) {
	return new IpLookupService($app['service.db']);
};

$app['service.result.process'] = function($app) {
	return new ResultProcessorService(
		$app['service.db'],
		$app['db.url.load'],
		$app['db.probe.load'],
		$app['db.isp.load']
		);
};

function checkParameters($req, $params) {
	# check that required GET/POST parameters are present
	$keys = array_merge($req->request->keys(), $req->query->keys());
	foreach($params as $requiredParam) {
		if (!in_array($requiredParam, $keys)) {
			# throw if any are missing
			error_log("Missing parameter: $requiredParam");
			throw new InputError();
		}
	}
}

function checkProbe($probe) {
	# Probe status check, throw if not enabled
	if ($probe['enabled'] != 1) {
		throw new ProbeStateError();
	}
}
function checkUser($user) {
	# User status check, throw if not "ok"
	if ($user['status'] != 'ok') {
		throw new UserStatusError($row['status']);
	}
}

function checkAdministrator($user) {
	# Administrator privilege check, throw if not an admin
	checkUser($user); # also check general user status
	if ($user['administrator'] == 0) {
		throw new UserPrivsError();
	}
}

$app->error(function(APIException $e, $code) {
	$error_class = get_class($e);
	switch($error_class) {
		case "ConfigLoadError":
			$code = 404;
			$message = "Config version or format not found";
			break;
		case "ProbeLookupError":
		case "UserLookupError":
		case "UrlLookupError":
		case "IspLookupError":
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
		case 'UserPrivsError':
			$code = 403;
			$message = "User is not authorised to perform this action";
			break;
		case 'IpLookupError':
			$code = 500;
			$message = "An error occurred gathering IP information";
			break;
	};
	error_log("Error response: $code, $message, $error_class");
	return new JsonResponse(
		array('success'=>false, 'error'=>$message), $code
		);
});

$app->after(function(Request $request, Response $response) {
	# Set API version header on all responses

	global $APIVersion; // from DB.php
	$response->headers->set('API-Version', $APIVersion);
});

/* URL Endpoints */

$app->post('/submit/url', function(Request $req) use ($app) {
	/* Add a URL for testing */
	$conn = $app['service.db'];

	checkParameters($req, array('email','signature'));

	$row = $app['db.user.load']->load($req->get('email'));
	checkUser($row);

	Middleware::verifyUserMessage($req->get('url'), $row['secret'], 
		$req->get('signature')
	);

	# there is some badness here - URL is uniquely indexed to only the first 
	# 767 characters

	$conn->query(
		"insert ignore into urls(URL, hash, source, lastPolled, inserted) values (?,?,?,NULL, now())",
		array($req->get('url'), md5($req->get('url')), $req->get('source'))
		);
	# Because of the unique index (and the insert ignore) we have to query
	# to get the ID, instead of just using insert_id
	$url = $app['db.url.load']->load($req->get('url'));

	$conn->query(
		"insert into requests(urlID, userID, submission_info, created)
			values (?,?,?,now())",
		array($url['urlID'], $row['id'], $req->get('additional_data'))
		);
	$request_id = $conn->insert_id;

	$msgbody = json_encode(array('url'=>$req->get('url'), 'hash'=>md5($req->get('url'))));
	
	$ch = $app['service.amqp'];
	$ex = new AMQPExchange($ch);
	$ex->setName('org.blocked');
	$ex->publish($msgbody, 'url.org', AMQP_NOPARAM, array('priority'=>2));

	return $app->json(array('success' => true, 'uuid' => $request_id, 'hash' => md5($req->get('url'))), 201);
});
	
$app->get('/status/user',function(Request $req) use ($app) {
	# Get the status of a user
	$conn = $app['service.db'];

	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$row = $app['db.user.load']->load($req->get('email'));

	Middleware::verifyUserMessage( $req->get('email') .':'. $req->get('date'), 
		$row['secret'], $req->get('signature'));


	return $app->json(array('success'=>'true', 'status'=> $row['status']));
	
});


$app->post('/register/user', function(Request $req) use ($app) {
	# Add a new user
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
	checkUser($row);

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
	checkUser($row);

	$check_uuid = md5($req->get('probe_seed') . '-' . $row['probeHMAC']);
	if ($check_uuid != $req->get('probe_uuid')) {
		return $app->json(array(
			'success' => false,
			'error' => 'Probe seed and HMAC verification failed'
			), 403);
	}

	$secret = Middleware::generateSharedSecret();

	try {
		$conn->query("insert into probes (uuid,userID,secret,countrycode,type) values (?,?,?,?,?)",
			array(
				$req->get('probe_uuid'), $row['id'], $secret, 
				$req->get('country_code'), $req->get('probe_type')
				)
			);
	}
	catch (DatabaseError $e) {
		if ($e->getCode() == 1062) {
			throw new ConflictError("A probe with this UUID already exists");
		} else {
			throw $e;
		}
	}

	return $app->json(array(
		'success' => true,
		'secret' => $secret), 201);
});

$app->get('/request/httpt', function(Request $req) use ($app) {
	checkParameters($req, array('probe_uuid','signature','network_name'));

	$probe = $app['db.probe.load']->load($req->get('probe_uuid'));
	checkProbe($probe);
	Middleware::verifyUserMessage($req->get('probe_uuid'),  $probe['secret'], $req->get('signature'));
	
	# Get the ISP details
	$isp = $app['db.isp.load']->load($req->get('network_name'));

	$ch = $app['service.amqp'];

	$q = new AMQPQueue($ch);
	# TODO: decide how to choose public vs. private queue,
	# but use org for now
	$q->setName('url.' . get_queue_name($isp['name']) . '.org');
	$q->setFlags(AMQP_PASSIVE);
	try {
		$q->declare();
	} catch (AMQPQueueException $e) {
		return $app->json(array(
			'success' => false,
			'error' => "Queue for {$isp['name']} does not exist",
		), 404);
	}

	$batch = (int)$req->get('batchsize', 1);
	$urls = array();
	for ($i = 0; $i < $batch; $i++) {
		$msg = $q->get();
		if ($msg === false) {
			break;
		}
		$q->ack($msg->getDeliveryTag());
		$urls[] = (array)json_decode($msg->getBody());
	}

	if (count($urls) == 0) {
		return $app->json(array(
			'success' => false,
			'error' => 'No queued URLs found'
			), 404);
	}
	error_log("Got URL: " . $urls[0]['url']);
	$ret = array(
		'success' => true,
		);
	if ($batch > 1) {
		$ret['urls'] = $urls;
	} else {
		$ret['url'] = $urls[0]['url'];
		$ret['hash'] = $urls[0]['hash'];
	}
	#$app['db.probe.load']->updateReqSent($probe['uuid']);

	return $app->json($ret, 200);
});

$app->post('/response/httpt', function(Request $req) use ($app) {
	checkParameters($req, 
		array('probe_uuid','url','config','ip_network','status',
		'http_status','date','signature','network_name')
		);

	$probe = $app['db.probe.load']->load($req->get('probe_uuid'));
	checkProbe($probe);

	Middleware::checkMessageTimestamp($req->get('date'));

	Middleware::verifyUserMessage(
		implode(":", array(
			$req->get('probe_uuid'),
			$req->get('url'),
			$req->get('status'),
			$req->get('date'),
			$req->get('config')
			)
		),
		$probe['secret'],
		$req->get('signature')
	);

	$result = array(
		'config' => $req->get('config'),
		'ip_network' => $req->get('ip_network'),
		'network_name' => $req->get('network_name'),
		'status' => $req->get('status'),
		'http_status' => $req->get('http_status'),
		'url' => $req->get('url')
		);

	$app['service.result.process']->process_result($result, $probe);

	return $app->json(array('success' => true, 'status' => 'ok'));

});

$app->get('/config/{version}', function (Request $req, $version) use ($app) {
	error_log("Version: $version");
	if (!$version) {
		throw new InputError();
	}
	if ($version != 'latest' && !is_numeric($version)) {
		throw new InputError();
	}
	$format = $req->get('format');
	if (!$format) {
		$format = "json";
	}
	if ($format != "json") {
		/* support XML as well eventually */
		throw new InputError();
	}

		
	// fetch and return config here

	$configfile = __DIR__ . "/../../config/" . $version . "." . $format;
	error_log("Config file: $configfile");

	$content = file_get_contents($configfile);
	if (!$content) {
		throw new ConfigLoadError();
	}
		
	
	return $content;
});

$app->post('/update/gcm', function(Request $req) use ($app) {
	checkParameters($req, array('gcm_id','probe_uuid','signature'));

	$probe = $app['db.probe.load']->load($req->get('probe_uuid'));

	Middleware::verifyUserMessage($req->get('gcm_id'), $probe['secret'],  $req->get('signature'));

	$conn = $app['service.db'];
	$conn->query("update probes set gcmRegID=?, lastSeen=now(), gcmType=?, frequency=? where uuid=?",
		array(
			$req->get('gcm_id'),
			$req->get('gcm_type'),
			$req->get('frequency'),
			$req->get('probe_uuid'),
			));

	return $app->json(array('success'=>true,'status'=>'ok'));
});

$app->get('/status/ip/{client_ip}', function(Request $req, $client_ip) use ($app) {
	# Get information about an IP.  If {client_ip} is omitted, use request originating IP
	checkParameters($req, array('probe_uuid','signature','date'));

	$probe = $app['db.probe.load']->load($req->get('probe_uuid'));
	checkProbe($probe);

	Middleware::checkMessageTimestamp($req->get('date'));

	Middleware::verifyUserMessage($req->get('date'), $probe['secret'], $req->get('signature') );

	if ($client_ip) {
		$ip = $client_ip;
	} else { 
		$ip = $req->getClientIp();
	}
	
	$descr = $app['service.ip.query']->lookup($ip);

	/* use standardised name from the database if possible */
	try {
		$isp = $app['db.isp.load']->load($descr);
		$descr = $isp['name'];
	}
	catch (IspLookupError $e) {
		error_log("Caught failed lookup");
		$descr = $app['db.isp.load']->create($descr);
	}

	return $app->json(array('success'=>true,'ip'=>$ip, 'isp'=>$descr));
})
->value('client_ip',''); # make client_ip arg optional


#--------- Administrator Functions

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
	while ($row = $rs->fetch_assoc()) {
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
	$conn->query("UPDATE users set status = ? where email = ?",
		array($req->get('status'), $user));

	if ($conn->affected_rows == 0) {
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
	$conn->query("UPDATE probes set enabled = ? where uuid = ?",
		array($req->get('status') == "enabled" ? 1 : 0, $uuid));

	if ($conn->affected_rows == 0) {
		throw new ProbeLookupError();
	}

	return $app->json(array('success'=> true, "status"=> $req->get('status'), "email"=> $user));
});

$app->get('/status/url', function (Request $req) use ($app) {
	checkParameters($req, array('url','email','signature'));

	$user = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('url'), $user['secret'], $req->get('signature'));
	error_log("URL: " . $req->get('url'));
	$url = $app['db.url.load']->load($req->get('url'));

	$conn = $app['service.db'];

	# build a results array for each ISP
	$result = $conn->query("select name from isps order by name",array());
	$output = array();

	# this can be driven from a status table or cached if we need better efficiency
	while ($isp = $result->fetch_assoc()) {
		$out = array('network_name' => $isp['name']);

		# get latest status and result

		$result2 = $conn->query("select status, created from results where urlid = ? and network_name = ? order by created desc limit 1",
			array($url['urlID'], $isp['name']));
		$row = $result2->fetch_row();
		if (!$row) {
			# no results for this ISP/URL combination
			continue;
		}
		$out['status'] = $row[0];
		$out['status_timestamp'] = $row[1];

		# get last blocked time
		$result2 = $conn->query("
			select created from results where urlid = ? and network_name = ? 
			and status = 'blocked'
			order by created desc limit 1",
			array($url['urlID'], $isp['name']));
		$row = $result2->fetch_row();

		$out['last_blocked_timestamp'] = $row[0];

		$output[] = $out;
	}

	return $app->json(array('success' => true, "url" => $url['URL'], "results" => $output));
});

$app->get('/status/stats', function( Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	$user = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $user['secret'], $req->get('signature'));

	$conn = $app['service.db'];
	$result = $conn->query("
	select count(*) from urls 
		", array());

	$row = $result->fetch_row();

	$stats = array(
		'urls_reported' => $row[0],
		);

	$result = $conn->query("select count(distinct urlid) from results",array());
	$row = $result->fetch_row();
	$stats['urls_tested'] = $row[0];

	$result = $conn->query("select count(distinct urlid) from results where status = 'blocked'", array());
	$row = $result->fetch_row();
	$stats['blocked_sites_detected'] = $row[0];

	return $app->json(array('success' => true, "stats" => $stats));
});



$app->run();

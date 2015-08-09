<?php

require_once __DIR__."/../../backend/silex/vendor/autoload.php";

include_once "libs/DB.php";
include_once "libs/amqp.php";
include_once "libs/pki.php";
include_once "libs/password.php";
include_once "libs/exceptions.php";
include_once "libs/services.php";
include_once "libs/url.php";

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
$app['db.contact.load'] = function($app) {
	return new ContactLoader($app['service.db']);
};
$app['db.url.load'] = function($app) {
	return new UrlLoader($app['service.db']);
};
$app['db.isp.load'] = function($app) {
	return new IspLoader($app['service.db']);
};
$app['service.ip.query'] = function($app) use ($WHOIS_SERVER) {
	return new IpLookupService($app['service.db'], "WHOIS", $WHOIS_SERVER);
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
			throw new InputError("Missing parameter: $requiredParam");
		}

		if (trim($req->get($requiredParam)) == '') {
			throw new InputError("$requiredParam requires a value");
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
		case "TokenLookupError":
			$code = 404;
			$message = "No matches in DB, please contact ORG support";
			break;
		case "InputError":
			$code = 400;
			$message = "One or more required parameters missing or invalid: " . $e->getMessage();
			break;
		case "InvalidTokenError":
			$code = 400;
			$message = "The supplied verification token is not in a valid format";
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
		case 'BadUrlError':
			$code = 400;
			$message = $e->getMessage();
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
	
	global $SUBMIT_ROUTING_KEY;

	checkParameters($req, array('email','signature','url'));

	$row = $app['db.user.load']->load($req->get('email'));
	checkUser($row);

	Middleware::verifyUserMessage($req->get('url'), $row['secret'], 
		$req->get('signature')
	);

	if (trim($req->get('url')) == '') {
		throw new InputError();
	}

    # Work around parser error in empty() function for PHP versions < 5.5
    $hasContactEmail = $req->get('contactemail');

    if (!empty($hasContactEmail)) {
        # Visitor provided a contact address; store it as a contact.
        # joinlist flag is cleared unless set explicitly in request.
        #
        # If contact exists, don't duplicate record, but update joinlist flag
        # and fullname unless they would be cleared
        $conn->query(
            "INSERT INTO contacts
            SET
                email=?,
                joinlist=?,
                fullName=?
            ON DUPLICATE KEY UPDATE
                joinlist=IF(joinlist=false, VALUES(joinlist), joinlist),
                fullName=IF(VALUES(fullName)='', fullName, VALUES(fullName));
            ",
            array($req->get('contactemail'), $req->get('joinlist', false), $req->get('fullname'))
        );
        # Grab the contact ID to insert into the requests table below
        $contact = $app['db.contact.load']->load($req->get('contactemail'));
    }
    else {
        $contact = NULL;
    }

	$urltext = normalize_url($req->get('url'));

	$newurl = $app['db.url.load']->insert($urltext, $req->get('source','user'));

	# Because of the unique index (and the insert ignore) we have to query
	# to get the ID, instead of just using insert_id
	$url = $app['db.url.load']->load($urltext);

	# always record the request, even if we didn't queue it
    $conn->query(
        "insert into requests (urlID, userID, contactID, submission_info, information, allowcontact, created)
            values (?,?,?,?,?,?,now())",
        array($url['urlID'], $row['id'], $contact['id'], $req->get('additional_data'), $req->get('information'), $req->get('allowcontact', false))
    );

	$request_id = $conn->insert_id;

	if ($contact != null && $req->get('subscribereports',false) ) {
		$conn->query(
			"INSERT INTO url_subscriptions (urlID, contactID, subscribereports, created)
			VALUES (?,?,?,NOW())
			ON DUPLICATE KEY UPDATE
				subscribereports=VALUES(subscribereports), 
				created=VALUES(created)",
			array($url['urlID'], $contact['id'], $req->get('subscribereports', false) )
		);

		# create verification token for email subscribe
		# needs an update because we're using the row ID as a salt of sorts

		# should probably handle duplicated tokens here (just because it's possible)
		$conn->query("update url_subscriptions set token = concat('A',md5(concat(id, '-', urlID, '-', contactID,'-',?)))
			where urlID = ? and contactID = ?",
			array(Middleware::generateSharedSecret(10), $url['urlID'], $contact['id'])
			);



		# TODO: send verify email
	}

	# test the lastPolled date
	# if it was last tested more than a day ago, send it to the queue

	# beware shortcut logic - checkLastPolled is not evaluated for new urls
	# checkLastPolled also updates the timestamp
	if ($newurl || $app['db.url.load']->checkLastPolled($url['urlID'])) {

		$queued = true;

		# send the ascii-IDN version for ooniprobe compatibility
		$msgbody = json_encode(array('url'=>url_ascii($urltext), 'hash'=>md5($urltext)));

		$ch = $app['service.amqp'];
		$ex = new AMQPExchange($ch);
		$ex->setName('org.blocked');
		$ex->publish($msgbody, $SUBMIT_ROUTING_KEY, AMQP_NOPARAM, array('priority'=>2));

	} else {
		$queued = false;
	}

	# return request details, with queue status for the frontend

	return $app->json(array(
		'success' => true,
		'uuid' => $request_id,
		'hash' => md5($urltext),
		'queued' => $queued
	), 201);
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

$app->get('/request/httpt/ooni/{queuesuffix}', function(Request $req, $queuesuffix) use ($app) {
	checkParameters($req, array('probe_uuid','signature'));

	# Get the ISP details
	$network_name = $app['service.ip.query']->lookup($req->getClientIp());
	$isp = $app['db.isp.load']->load($network_name);

	$probe = $app['db.probe.load']->load($req->get('probe_uuid'));
	checkProbe($probe);
	# remove ISP name from auth for now, and just use hashed uuid 
	# (allows portability between machines on different networks)
	Middleware::verifyUserMessage(
		$req->get('probe_uuid'),
		$probe['secret'], 
		$req->get('signature')
		);
	

	if (!$isp['queue_name']) {
		return $app->json(array(
			'success' => false,
			'error' => 'No queue found'
			), 404);
	}

	$msgcount = 0;
	$batch = 25;
	$urls = array();

	$ch = $app['service.amqp'];
	$q = new AMQPQueue($ch);

	$queuename = 'url.' . $isp['queue_name'] . '.' . $queuesuffix;
	error_log("Reading from: {$queuename}");
	$q->setName($queuename);
	$q->setFlags(AMQP_PASSIVE);
	try {
		# passive gives us an error if the queue does not exist,
		# but doesn't create it
		$q->declare();
	} catch (AMQPQueueException $e) {
		return $app->json(array(
			'success' => false,
			'error' => "Queue for {$isp['name']} does not exist",
		), 404);
	}

	while ($msgcount < $batch) {
		$msg = $q->get();
		if ($msg === false) {
			# move onto the next queue
			break;
		}
		$q->ack($msg->getDeliveryTag());
		$urldata = (array)json_decode($msg->getBody());
		$urls[] = $urldata['url'];
		$msgcount ++;
	}

	if (count($urls) == 0) {
		return $app->json(array(
			'success' => false,
			'error' => 'No queued URLs found'
			), 404);
	}

	error_log("Got URLs: " . $msgcount);
	$app['db.probe.load']->updateReqSent($probe['uuid'], $msgcount);

	return new Response(implode("\r\n", $urls), 200);

});

$app->get('/request/httpt', function(Request $req) use ($app) {
	checkParameters($req, array('probe_uuid','signature','network_name'));

	$probe = $app['db.probe.load']->load($req->get('probe_uuid'));
	checkProbe($probe);
	Middleware::verifyUserMessage($req->get('probe_uuid'),  $probe['secret'], $req->get('signature'));
	
	# Get the ISP details
	$isp = $app['db.isp.load']->load($req->get('network_name'));

	if (!$isp['queue_name']) {
		return $app->json(array(
			'success' => false,
			'error' => 'No queue found'
			), 404);
	}

	$ch = $app['service.amqp'];

	error_log("Probe type: {$probe['type']}");
	if ($probe['isPublic'] == 0) {
		# ORG probes can use the ORG queue and fall back on the public queue
		# when the ORG queue is empty
		$queuelist = array('org','public');
	} else {
		# public probes can only use the week-behind public queue
		$queuelist = array('public');
	}

	$msgcount = 0;
	$batch = (int)$req->get('batchsize', 1);
	$urls = array();

	foreach ($queuelist as $queuesuffix) {
		$q = new AMQPQueue($ch);

		$queuename = 'url.' . $isp['queue_name'] . '.' . $queuesuffix;
		error_log("Reading from: {$queuename}");
		$q->setName($queuename);
		$q->setFlags(AMQP_PASSIVE);
		try {
			# passive gives us an error if the queue does not exist,
			# but doesn't create it
			$q->declare();
		} catch (AMQPQueueException $e) {
			return $app->json(array(
				'success' => false,
				'error' => "Queue for {$isp['name']} does not exist",
			), 404);
		}

		while ($msgcount < $batch) {
			$msg = $q->get();
			if ($msg === false) {
				# move onto the next queue
				break;
			}
			$q->ack($msg->getDeliveryTag());
			$urls[] = (array)json_decode($msg->getBody());
			$msgcount ++;
		}
		if ($msgcount == $batch) {
			# move onto next queue unless we've already got the right number
			# of messages
			break;
		}
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
	$app['db.probe.load']->updateReqSent($probe['uuid'], $msgcount);

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
		'url' => $req->get('url'),
		'category' => $req->get('category'),
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

$app->get('/status/ip/{client_ip}', function(Request $req, $client_ip) use ($app, $AUTO_CREATE_QUEUES) {
	# Get information about an IP.  If {client_ip} is omitted, use request originating IP
	checkParameters($req, array('probe_uuid','signature','date'));

	#$probe = $app['db.probe.load']->load($req->get('probe_uuid'));
	#checkProbe($probe);

	#Middleware::checkMessageTimestamp($req->get('date'));

	#Middleware::verifyUserMessage($req->get('date'), $probe['secret'], $req->get('signature') );

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
		$isp = $app['db.isp.load']->create($descr, $AUTO_CREATE_QUEUES);

        if ($AUTO_CREATE_QUEUES) {
            $ch = $app['service.amqp'];
            create_queue($ch, 'url.' . $isp['queue_name'] . '.org', 'url.org');
            create_queue($ch, 'url.' . $isp['queue_name'] . '.public', 'url.public');
        }

	}

	return $app->json(array('success'=>true,'ip'=>$ip, 'isp'=>$descr));
})
->value('client_ip',''); # make client_ip arg optional


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

/*  -------^---^---^---- End Administrator functions ... */

$app->get('/status/url', function (Request $req) use ($app) {
	checkParameters($req, array('url','email','signature'));

	$user = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('url'), $user['secret'], $req->get('signature'));

	$urltext = normalize_url($req->get('url'));

	error_log("URL: " . $req->get('url') . "; " . $urltext);
	$url = $app['db.url.load']->load($urltext);

	$conn = $app['service.db'];

	# Fetch results from status summary table, left joining to get last blocked time
	$result = $conn->query("select isps.description, l.status, l.created, max(r.created), min(r.created), l.category, l.blocktype
		from url_latest_status l 
		inner join isps on isps.name = l.network_name
		left join results r on r.network_name = l.network_name and r.urlID = l.urlID and r.status = 'blocked' 
		where l.urlID = ? and isps.show_results = 1
		group by l.network_name",
		array($url['urlID']));

	$output = array();

	while ($row = $result->fetch_row()) {
		$out = array('network_name' => $row[0]);

		# get latest status and result

		$out['status'] = $row[1];
		$out['status_timestamp'] = $row[2];
		$out['last_blocked_timestamp'] = $row[3];
		$out['first_blocked_timestamp'] = $row[4];
		$out['category'] = $row[5];
		$out['blocktype'] = $row[6];

		$output[] = $out;
	}

	$categories = $app['db.url.load']->load_categories($url['urlID']);

	return $app->json(array(
		'success' => true, 
		"url" => $url['URL'], 
		"results" => $output,
		"url-status" => $url['status'],
		"categories" => $categories,
	));
});

$app->get('/status/stats', function( Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	$user = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $user['secret'], $req->get('signature'));

	$conn = $app['service.db'];

	$rs = $conn->query("select name, value from stats_cache", array());
	$stats = array();
	while($row = $rs->fetch_row()) {
		$stats[$row[0]] = (int)$row[1];
	}

	return $app->json(array('success' => true, "stats" => $stats));
});


$app->get('/status/daily-stats', function (Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	$user = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $user['secret'], $req->get('signature'));

	$conn = $app['service.db'];
    if ($req->query->has('days') || (!$req->query->has('start') && !$req->query->has('end'))) {
        $off = $req->get('days', 84);

        $rs = $conn->query("select stats_date, blocked from daily_stats where stats_date >= date_sub(current_date, INTERVAL ? DAY) order by stats_date",
            array($off)
            );
    } elseif ($req->query->has('start') && $req->query->has('end')) {
        $rs = $conn->query("select stats_date, blocked from daily_stats where stats_date between ? and ? order by stats_date",
            array($req->get('start'), $req->get('end')));

    } else {
        throw new InputError("Requires days or start&end parameters");
    }

	$stats = array();
	while($row = $rs->fetch_row()) {
		$stats[$row[0]] = array('blocked' => (int)$row[1]);
	}

	return $app->json(array('success' => true, "stats" => $stats));

});

$app->get('/status/isp-stats', function(Request $req) use ($app) {
	function mkint($v) {
		return (int)$v;
	}

	checkParameters($req, array('email','signature','date'));
	$user = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $user['secret'], $req->get('signature'));

	$conn = $app['service.db'];

	$rs = $conn->query("select isps.description, ok, blocked, timeout, error, dnsfail, total
	from isp_stats_cache
	inner join isps on isps.name = isp_stats_cache.network_name
	order by isps.description", array());

	$output = array();
	while ($row = $rs->fetch_assoc()) {
		$net = $row['description'];
		unset($row['description']);
		$output[$net] = array_map("mkint", $row);
	}


	return $app->json(array('success' => true, 'isp-stats' => $output));
});

class StreamResultProcessor {
	function __construct($conn) {
		$this->conn = $conn;
	}


	function result_callback($msg, $queue) {
		#print $msg->getBody() . "\n";
		$data =(array)json_decode($msg->getBody());

	# Fetch results from status summary table, left joining to get last blocked time
	$result = $this->conn->query("select isps.description, l.status, l.created, max(r.created), min(r.created), l.category 
		from url_latest_status l 
		inner join isps on isps.name = l.network_name
		inner join urls_compat urls on urls.urlID = l.urlID
		left join results r on r.network_name = l.network_name and r.urlID = l.urlID and r.status = 'blocked' 
		where urls.url = ? and l.network_name = ?
		group by l.network_name",
		array($data['url'], $data['network_name']));

		$row = $result->fetch_row();

		$data['network_name'] = $row[0];
		$data['status_timestamp'] = $row[2];
		$data['last_blocked_timestamp'] = $row[3];

		print json_encode($data) . "\n\n";

		ob_flush();
		$queue->ack($msg->getDeliveryTag());
	}
}

$app->get('/stream/results', function (Request $req) use ($app) {
	/* experimental endpoint that streams results from the AMQP public 
	results queue using chunked encoding.  A blocking client socket
	will be able to read single-line json statements giving the results for
	each ISP in real time.  For best results, the call to /stream/results
	should be made BEFORE submitting the URL to the API.
	*/


	// it's probably a bit rude to do this inside silex
	ini_set("output_buffering", "off");
	ob_implicit_flush(true);

	checkParameters($req, array('email','signature','url','date'));

	$user = $app['db.user.load']->load($req->get('email'));

	Middleware::checkMessageTimestamp($req->get('date'));
	Middleware::verifyUserMessage($req->get('url').':'.$req->get('date'), $user['secret'], $req->get('signature'));

	$timeout = (int)$req->get('timeout');
	if ($timeout) {
		if (!(5 <= $timeout && $timeout <= 300)) {
			return $app->json(array(
				"success" => false, 
				"error" => "Invalid timeout value"
			), 400);
		}
	} else {
		$timeout = 15;
	}
	error_log("Timeout set to: $timeout");

	list($amqpconn, $ch) = amqp_connect_full();
	$amqpconn->setTimeout($timeout);

	$url = normalize_url($req->get('url'));
	$hash = md5($url);

	try {
		$q = new AMQPQueue($ch);
		$q->setName("result." . $hash);

		$q->setFlags(AMQP_AUTODELETE|AMQP_EXCLUSIVE);
		$q->declare();
		$q->bind("org.results", "results.*." . $hash);
		

		$tag = $hash . "-" . time();
		print json_encode(array(
			"type" => "status", 
			"tag" => $tag,
			"hash" => $hash,
			"url" => $url, 
			));
		print "\n";
		ob_flush();

		$conn = $app['service.db'];
		# Fetch results from status summary table, left joining to get last blocked time
		$result = $conn->query("select isps.description, l.status, l.created, max(r.created), min(r.created), l.category 
		from url_latest_status l 
		inner join urls_compat urls on urls.urlid = l.urlid
		inner join isps on isps.name = l.network_name
		left join results r on r.network_name = l.network_name and r.urlID = l.urlID and r.status = 'blocked' 
		where urls.url = ? and isps.show_results = 1
		group by l.network_name",
		array($url));

		while ($row = $result->fetch_row()) {
			$out = array('network_name' => $row[0]);

			# get latest status and result

			$out['status'] = $row[1];
			$out['status_timestamp'] = $row[2];
			$out['last_blocked_timestamp'] = $row[3];
			$out['first_blocked_timestamp'] = $row[4];
			$out['category'] = $row[5];

			print json_encode($out) . "\n";
			ob_flush();

		}

		$processor = new StreamResultProcessor($app['service.db']);
		$q->consume(array($processor,"result_callback"), AMQP_NOPARAM, $tag);
	} catch (AMQPQueueException $e) {
		return $app->json(array(
			"success" => false, 
			"type" => "error",
			"msg" => $e->getMessage()
		), 500);
	} catch (AMQPConnectionException $e) {
		$q->cancel($tag);
	};


	return $app->json(array('success' => true, "type" => "close", "tag" => $tag));

});

$app->post('/verify/email', function (Request $req) use ($app) {
	checkParameters($req, array('email','signature','token','date'));
	$user = $app['db.user.load']->load($req->get('email'));

	Middleware::checkMessageTimestamp($req->get('date'));
	Middleware::verifyUserMessage($req->get('token').':'.$req->get('date'), $user['secret'], $req->get('signature'));

	$conn = $app['service.db'];
	if (substr($req->get('token'), 0, 1) == 'A') {
		# URL subscription token

		$conn->autocommit(FALSE);
		$conn->query("BEGIN",array());
		try {
			$result = $conn->query("select contactID from url_subscriptions 
				where token = ?",
				array($req->get('token'))
			);
			$row = $result->fetch_array();
			if (!$row) {
				throw new TokenLookupError();
			}
			$conn->query("update contacts set verified = 1 where id = ?",
				array($row[0])
			);
			$conn->query("update url_subscriptions set verified = 1, token = null 
				where verified = 0 and token = ?",
				array($req->get('token'))
			);

			if ($conn->affected_rows != 1) {
				throw new TokenLookupError();
			}
			$conn->commit();
			$conn->autocommit(TRUE);
		} catch (Exception $err) {
			error_log("Rolling back");
			$conn->rollback();
			$conn->autocommit(TRUE);
			throw $err;
		} 
	} else {
		throw new InvalidTokenError();
	}

	return $app->json(array('success' => true, 'verified' => true));

});

## OONI REPORT API

$app->post('/report', function (Request $req) use ($app) {
	$conn = $app['service.db'];
	$data = json_decode($req->getContent());

	$probe = $app['db.probe.load']->load($data->probe_uuid);
	#Middleware::checkMessageTimestamp($data['probe_ts']);
	#Middleware::verifyUserMessage($data->probe_ts, $probe['secret'], $data->probe_signature);

	$conn->query("insert into reports(data, created) values (?, now())",
		array($req->getContent()));
	$id = $conn->insert_id;
	$output = array(
		'report_id' => "$id",
		'backend_version' => 0.01,
	);

	return $app->json($output, 201);
});	

$app->put("/report", function (Request $req) use ($app) {
	$conn = $app['service.db'];
	$data = json_decode($req->getContent());

	$conn->query("insert into report_entries(report_id, data, created) values (?,?,now())",
	array($data->report_id, $data->content));
	$entry_id = $conn->insert_id;
	$conn->commit();

	$ch = $app['service.amqp'];
	$ex = new AMQPExchange($ch);
	$ex->setName('org.blocked');
	$ex->publish((string)$entry_id, 'ooniresults.' . $data->report_id, AMQP_NOPARAM, array('priority'=>2));
	
	
	return $app->json(array(),201);

});

$app->post('/report/{id}/close', function (Request $req, $id) use ($app) {
	$conn = $app['service.db'];

	$conn->query("update reports set complete=1 where id=?",
		array($id));

    $res = $conn->query("select * from reports where id = ?",
        array($id));
    $report = $res->fetch_assoc();
    $report_data = json_decode($report['data']);

    # possibly temporary - only send report for those in the 
    # public queue, since the others don't have the page request
    # body

    if ($report_data['probe_queue'] == 'public') {
        
        $ch = $app['service.amqp'];
        $ex = new AMQPExchange($ch);
        $ex->setName('org.blocked');
        $ex->publish((string)$id, 'oonireports.' . $report_data['probe_queue'], 
            AMQP_NOPARAM, array('priority'=>2));

    }

	return $app->json(array(),200);
});

$app->run();

<?php

require_once __DIR__."/../../backend/silex/vendor/autoload.php";

include_once "libs/DB.php";
include_once "libs/amqp.php";
include_once "libs/pki.php";
include_once "libs/password.php";
include_once "libs/exceptions.php";
include_once "libs/services.php";
include_once "libs/url.php";
include_once "libs/email.php";
include_once "libs/logging.php";

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

$CORS_HEADERS = array(
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'GET, OPTIONS, PUT, POST',
    'Access-Control-Allow-Headers' => 'content-type',
);


$app = new Silex\Application();
# $app['debug'] = true;

$app['service.db'] = $app->share(function() {
    return db_connect();
});

$app['service.dynamo'] = $app->share(function() {
    return new DynamoWrapper(
        AWS_DYNAMODB_ACCESS_KEY,
        AWS_DYNAMODB_SECRET_KEY,
        AWS_DYNAMODB_URL
    );
});


$app['service.redis.cache'] = $app->share(function(){
    return redis_connect("cache");
});

$app['service.amqp'] = $app->share(function() {
	return amqp_connect();
});

$app['service.queue'] = $app->share(function($app) {
    global $SUBMIT_ROUTING_KEY;

    return new AMQPQueueService($app['service.amqp'],
        $SUBMIT_ROUTING_KEY);
});

$app['service.elastic'] = $app->share(function($app) {
    global $ELASTIC;

    return new ElasticService($ELASTIC);
});

$loader = new Twig_Loader_Filesystem("templates");
$app['service.template'] = new Twig_Environment($loader, array(
    'cache' => false,
    'debug' => true,
    'autoescape' => false
));
$app['service.template']->addExtension(new Twig_Extension_Debug());

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
$app['service.ip.query'] = function($app) {
	return new IpLookupService($app['service.db']);
};
$app['db.category.load'] = function($app) {
	return new DMOZCategoryLoader($app['service.db']);
};
$app['db.ispreport.load'] = function($app) {
	return new ISPReportLoader($app['service.db']);
};
$app['db.blacklist.load'] = function($app) {
	return new BlacklistLoader($app['service.db']);
};
$app['db.courtorder.load'] = function($app) {
	return new CourtOrderLoader($app['service.db']);
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
	    if ($requiredParam == "email" && @$_SERVER['PHP_AUTH_USER']) {
	        continue;
        }
        if ($requiredParam == "signature" && @$_SERVER['PHP_AUTH_PW']) {
            continue;
        }
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
		throw new UserStatusError($user['status']);
	}
}

function checkAdministrator($user) {
	# Administrator privilege check, throw if not an admin
	checkUser($user); # also check general user status
	if ($user['administrator'] == 0) {
		throw new UserPrivsError();
	}
}

function split_pg_array($value) {
    // splits a {value1,value2,value3} array into: array('value1','value2','value3')
    return explode(",", substr($value, 1, -1));

}

function checkAuth($app, $req, $key=null, $admin=false, $check_date=true) {
    if ($req->getUser()) {
        // check basic auth credentials against database
        $user = $app['db.user.load']->load($req->getUser());
        if ($user['secret'] != $req->getPassword()) {
            error_log("Password authentication failed");
            throw new UserLookupError();
        }
    } else {
        // older signature verification method
        $user = $app['db.user.load']->load($req->get('email'));
        if ($key) {
            if (is_array($key)) {
                $hashvalues = array();
                foreach ($key as $f) {
                    $hashvalues[] = $req->get($f);
                }
                $sigstring = implode(":", $hashvalues);
            } elseif (is_string($key)) {
                $sigstring = $key;
            }
            if ($check_date) {
                Middleware::checkMessageTimestamp($req->get('date'));
            }
            Middleware::verifyUserMessage($sigstring, $user['secret'], $req->get('signature'));
        }
    }

    if ($admin) {
        checkAdministrator($user);
    } else {
        checkUser($user);
    }

    return $user;
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
			$message = "$error_class: No matches in DB, please contact ORG support";
			break;
		case "InputError":
			$code = 400;
			$message = "One or more required parameters missing or invalid: " . $e->getMessage();
			break;
        case "InvalidEmailError":
            $code = 400;
            $message = "Invalid email address supplied";
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
        Case 'ContactPermissionDeniedError':
            $code = 403;
            $message = "Contact email is disallowed";
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
		case 'BadUrlError':
        case 'InvalidSortError':
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

// controller includes

include_once "ctrl/admin.inc.php";


/* URL Endpoints */

$app->get('/search/url', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','q'));
    $q = $req->get('q');
    $page = $req->get('page', 0);
	checkAuth($app, $req, ['q']);

    $exclude_adult = $req->get('exclude_adult', 0);
    if ($exclude_adult) {
        $query = $app['service.db']->query("select term from search_ignore_terms where enabled = true order by term", array());
        $excluded_terms = array();
        foreach($query as $row) {
            $excluded_terms[] = $row['term'];
        }
    } else {
        $excluded_terms = array();
    }

    $networks = $req->get('networks', null);
    if (!is_null($networks)) {
        if (!is_array($networks)) {
            $networks = array($networks);
        }
        if ($networks[0] == '_mobile' || $networks[0] == '_fixed') {
            $query = $app['service.db']->query("select name from isps where isp_type = ? and queue_name is not null and show_results = 1 order by name", array(substr($networks[0],1)));
            $networks = array();

            foreach($query as $row) {
                $networks[] = strtolower($row['name']);
            }

        }
    }
    $exclude = $req->get('exclude', 0);

    $tld = $req->get('domain', null);

    $data = $app['service.elastic']->query(trim($q) . "*", '/urls', null, $page, 20, $excluded_terms, $networks, $exclude, $tld);
    $output = array(
        'success' => true,
        'sites' => $data->results,
        'count' => $data->count
        );
    foreach($output['sites'] as $site) {
       $urldata = $app['db.url.load']->loadByID($site->id);
       $site->last_reported = $urldata['last_reported'];
    }

    return $app->json($output);


});

$app->post('/submit/url', function(Request $req) use ($app) {
	/* Add a URL for testing */
	$conn = $app['service.db'];

	checkParameters($req, array('email','signature','url'));

	$row = checkAuth($app, $req, ['url']);

    $is_admin = $row['administrator'];

    if ($is_admin && $req->get('queue')) {
        # administrators can specify a queue to post to
        $target_queue = 'url.' . $req->get('queue');
    } else {
        $target_queue = null;
    }

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

        $contact = $app['db.contact.load']->insert(
            $req->get('contactemail'),
            $req->get('fullname'),
            $req->get('joinlist', false)
            );
    }
    else {
        $contact = NULL;
    }

	$urltext = normalize_url($req->get('url'));

    if ($req->get('tags', null) && $is_admin) {
        $tags = explode(':', $req->get('tags'));
    } else {
        $tags = array();
    }

    $newurl = $app['db.url.load']->insert($urltext, $req->get('source','user'), $tags);

	# Because of the unique index (and the insert ignore) we have to query
	# to get the ID, instead of just using insert_id
	$url = $app['db.url.load']->load($urltext);
	$app['db.url.load']->update_url_hierarchy($url);


	# always record the request, even if we didn't queue it
    $args = array(
            $url['urlid'],
            $row['id'],
            ($contact ? $contact['id'] : null),
            $req->get('additional_data'),
            $req->get('information'),
            $req->get('allowcontact', 0)
            );

    $q = $conn->query(
        "insert into requests (urlID, userID, contactID, submission_info, information, allowcontact, created)
            values (?,?,?,?,?,?,now()) returning id as id",
        $args
    );

	$request_id = $q->fetchColumn();
    if ($req->get('additional_data')) {
        $additional = array();
        parse_str($req->get('additional_data'), $additional);
        foreach ($additional as $k => $v) {
            $conn->query(
                "insert into requests_additional_data(request_id, name, value, created)
                    values (?,?,?,now())",
                array($request_id, $k, $v)
                );
        }

    }

	if ($contact != null && $req->get('subscribereports',false) ) {
		$result = $conn->query(
            "SELECT insert_url_subscription(?,?,?)",
			array($url['urlid'], $contact['id'], $req->get('subscribereports', false) )
            );
        $row = $result->fetch(PDO::FETCH_NUM);
        debug_log("Inserted subscription: {$row[0]}");

		# create verification token for email subscribe
		# needs an update because we're using the row ID as a salt of sorts

		# should probably handle duplicated tokens here (just because it's possible)
		$r = $conn->query("update url_subscriptions set token = 'A'||md5(id || '-' || urlID || '-' || contactid || '-' || ?)
			where id = ? returning token",
			array(Middleware::generateSharedSecret(10), $row[0])
			);
        $subscriberow = $r->fetch(PDO::FETCH_NUM);
        debug_log("generated token: {$subscriberow[0]}");

        if (defined('FEATURE_SEND_SUBSCRIBE_EMAIL') && FEATURE_SEND_SUBSCRIBE_EMAIL == true) {
            $msg = new PHPMailer();
            $msg->setFrom(SITE_EMAIL, SITE_NAME);
            $msg->addAddress($req->get('contactemail'));
            $msg->Subject = "Confirm your blocking alert subscription";
            $msg->isHTML(false);
            $msg->CharSet = "utf-8";
            $msg->Body = $app['service.template']->render(
                'subscribe_email.txt',
                array(
                    'name' => $req->get('fullname'),
                    'url' => $req->get('url'),
                    'confirm_url' => CONFIRM_URL,
                    'token' => $subscriberow[0],
                    'site_url' => SITE_URL,
                    'site_name' => SITE_NAME,
                    'site_email' => SITE_EMAIL
                )
            );
            if (!$msg->Send()) {
                error_log("Unable to send message: " . $msg->ErrorInfo);
            }
        }
	}

	# test the lastPolled date
	# if it was last tested more than a day ago, send it to the queue

	# beware shortcut logic - checkLastPolled is not evaluated for new urls
	# checkLastPolled also updates the timestamp
    if ($is_admin && !is_null($target_queue) && $target_queue == 'url.none') {
        # admin requests that the URL is submitted but not queued
        debug_log("admin noqueue $urltext");
        $queued = false;
    } elseif ($newurl || $app['db.url.load']->checkLastPolled($url['urlid']) || ($is_admin && $req->get('force',0))) {

		$queued = true;

        $app['service.queue']->publish_url($urltext, $target_queue);

	} else {
		$queued = false;
	}

	# return request details, with queue status for the frontend
    $out = array(
           		'success' => true,
           		'uuid' => $request_id,
           		'hash' => md5($urltext),
           		'queued' => $queued
    );
    if ($is_admin) {
        $out['urlid'] = $url['urlid'];
    }
	return $app->json($out, 201);
});

$app->get('/status/user',function(Request $req) use ($app) {
	# Get the status of a user
	$conn = $app['service.db'];

	checkParameters($req, array('email','signature','date'));

	$row = checkAuth($app, $req, ['email','date']);

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
			"insert into users (email, password, probehmac, secret) VALUES (?,?,?,?)",
			array($email,$password,$probeHMAC,$secret)
			);
	}
	catch (DatabaseError $e) {
		if ($e->getCode() == ERR_DUPLICATE) {
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

	$conn = $app['service.db'];

	checkAuth($app, $req, ['email','date']);

	$probeHMAC = Middleware::generateSharedSecret(32);

	$conn->query("update users set probehmac = ? where email = ?",
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
	$row = checkAuth($app, $req, null, false, false);

	$check_uuid = md5($req->get('probe_seed') . '-' . $row['probehmac']);
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
		if ($e->getCode() == ERR_DUPLICATE) {
			throw new ConflictError("A probe with this UUID already exists");
		} else {
			throw $e;
		}
	}

	return $app->json(array(
		'success' => true,
		'secret' => $secret), 201);
});

$app->get('/config/{version}', function (Request $req, $version) use ($app) {
	debug_log("Version: $version");
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
		/* support XML or YAML as well eventually */
		throw new InputError();
	}

    if ($version == 'latest') {
        $version = CONFIG_LATEST;
    }

	// fetch and return config here

	$configfile = __DIR__ . "/../../config/" . $version . "." . $format;
	debug_log("Config file: $configfile");

	$content = file_get_contents($configfile);
	if (!$content) {
		throw new ConfigLoadError();
	}


	return $content;
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
		debug_log("Caught failed lookup");
		$queue_name =  get_queue_name($descr);
		$isp = $app['db.isp.load']->create($descr);

	}

	return $app->json(array('success'=>true,'ip'=>$ip, 'isp'=>$descr));
})
->value('client_ip',''); # make client_ip arg optional

$app->post('/status/probe/{probe}', function (Request $req, $probe) use ($app) {
    $data = json_decode($req->getContent(), true);    
    /*
     * Takes a data structure like:
     * {
     *   uuid: probe_uuid
     *   date: "20170301T142501"
     *   signature: sdfsdfsdfsdf
     * 
     *   owner_email: foo@bar.com
     *   owner_name: sdfsdfsdf
     *   owner_country_code: GB
     * 
     *   owner_org: WidgetCo
     *   owner_url: http://widget.co
     * }
     */
    
    $conn = $app['service.db'];
    $probe = $app['db.probe.load']->load($data['uuid']);
   	Middleware::verifyUserMessage($req->get('date'), $probe['secret'], $req->get('signature'));

    $app['db.probe.load']->updateLocation($data['uuid'], $data['owner_org']);
    $app['db.probe.load']->updateOwnerLink($data['uuid'], $data['owner_url']);
    
    try {
        $emailuser = $app['db.user.load']->load($data['owner_email']);
    } catch (UserLookupError $exc) {
        // no user found with that email
        $emailuser = array();
    }
    $user = $app['db.user.load']->loadByID($probe['userid']);
    
    if (@$emailuser['id'] && $emailuser['id'] != $user['id']) {
        // new user exists in the database but is not the currently assigned userid
        // update probe userid to new userid
        
        $app['db.probe.load']->updateUserID($probe['uuid'], $emailuser['id']);
        
    } else {
        
        // update current contact details
        $app['db.user.load']->updateContact($user['id'], $data['owner_name'], $data['owner_email'], $data['countrycode']);
        
    }
    
});

$app->get('/status/probes/{region}', function(Request $req, $region) use ($app) {
    checkParameters($req, array('email','signature','date'));

	$user = checkAuth($app, $req, ['date']);

	$conn = $app['service.db'];
    $result = $conn->query("select name, description, isp_status, fmtime(lastseen) as lastseen, probe_status, location, proberesprecv as tests_run,
        filter_level, filter_enabled,
        regions, owner_link, uuid
        from probes inner join isps on isp_id = isps.id
        where regions && makearray(?)
        order by lastseen desc",
        array($region));
    $output = array();
    foreach ($result as $row) {
        $row['regions'] = split_pg_array($row['regions']);
        $output[] = $row;
    }

    return $app->json(array('success'=>true, 'status'=> $output));

})->value('region','gb');

$app->get('/status/url', function (Request $req) use ($app) {
	checkParameters($req, array('url','email','signature'));

	$user = checkAuth($app, $req, ['url']);
    $admin = $user['administrator'];

    if ($admin && $req->get('normalize',1) == 0) {
        // allow an administrator to work on non-normalized URLs
        $urltext = $req->get('url');
    } else {
    	$urltext = normalize_url($req->get('url'));
    }
    
    if ($admin) {
        $admin_fields = ',results_base.remote_ip,results_base.resolved_ip,results_base.result_uuid,isps.isp_type';
    } else {
        $admin_fields = '';
    }

	debug_log("URL: " . $req->get('url') . "; " . $urltext);
	$url = $app['db.url.load']->load($urltext);

	$conn = $app['service.db'];

	# Fetch results from status summary table
	$result = $conn->query("select isps.description, l.status, fmtime(l.created) created,  l.category, l.blocktype,
        fmtime(first_blocked) as first_blocked, fmtime(last_blocked) as last_blocked,
        isps.name, isps.queue_name, results_base.final_url, isps.regions $admin_fields
		from url_latest_status l
		inner join isps on isps.name = l.network_name
        left join results_base on results_base.id = l.result_id
		where l.urlID = ? and isps.show_results = 1 and isps.regions && makearray(?)",
		array($url['urlid'], $req->get('region','gb'))
        );

	$output = array();

    foreach ($result as $row) {
		$out = array(
            'network_name' => $row['description'],
            'status' =>  $row['status'],
            'status_timestamp' =>  $row['created'],
            'last_blocked_timestamp' =>  $row['last_blocked'],
            'first_blocked_timestamp' =>  $row['first_blocked'],
            'category' =>  $row['category'],
            'blocktype' =>  $row['blocktype'],
            'regions' =>  split_pg_array($row['regions']),
            'network_id' =>  $row['name'],
            'last_report_timestamp' =>  $url['last_reported'],
            'isp_active' =>  ($row['queue_name'] != null),
            'isp_type' => $row['isp_type'],
            'final_url' => preg_replace('/&ipaddr=[0-9a-f\.:]*/','',$row['final_url'])
        );
        if ($admin) {
            $out['remote_ip'] = $row['remote_ip'];
            $out['result_uuid'] = $row['result_uuid'];
            $out['resolved_ip'] = $row['resolved_ip'];
        }
        $output[] = $out;
	}

    $related = $app['db.url.load']->get_related($url);

	$categories = $app['db.url.load']->load_categories($url['urlid']);
	$categories_ns = $app['db.url.load']->load_categories_ns($url['urlid']);

    $reports = array();
    foreach ($app['db.ispreport.load']->get_url_reports($url['urlid']) as $report) {
        if ($report['network_name'] == 'BBFC') {
            continue;
        }
        $reports[] = array(
            'report_type' => $report['report_type'],
            'created' => $report['created'],
            'network_name' => $report['network_name'],
            'name' => $report['allow_publish'] ? $report['name'] : "",
            'reply_count' => $report['reply_count'],
            'message' => ($report['allow_publish'] && $report['status'] != 'abuse') ? $report['message'] : ""
            );
    }

    $output = array(
		'success' => true,
		"url" => $url['url'],
        "title" => $url['title'],
		"results" => $output,
		"url-status" => $url['status'],
		"categories" => $categories,
		"categories_full" => $categories_ns,
        "reports" => $reports,
        "related" => $related,
        'last_report_timestamp' =>  $url['last_reported'],
        'blacklisted' => $app['db.blacklist.load']->check($url['url']),
	);
    if ($user['administrator']) {
        $output['tags'] = explode(",", substr($url['tags'], 1, -1));
        $output['whois_expiry'] = $url['whois_expiry'];
    }
	return $app->json($output);
});

$app->get('/status/stats', function( Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	$user = checkAuth($app, $req, ['date']);

	$conn = $app['service.db'];

	$rs = $conn->query("select name, value from stats_cache", array(), PDO::FETCH_NUM);
	$stats = array();
    foreach ($rs as $row) {
		$stats[$row[0]] = (int)$row[1];
	}

	return $app->json(array('success' => true, "stats" => $stats));
});

$app->get('/status/category-stats', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));
	$user = checkAuth($app, $req, ['date']);

	$conn = $app['service.db'];
    $stats = array();

    $q = $conn->query("select * from stats.category_stats order by network_name, category",
        array());
    foreach ($q as $row) {
        $stats[] = $row;
    }

    return $app->json(array('success' => true, 'stats' => $stats));

});

$app->get('/status/domain-stats', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));
    $user = checkAuth($app, $req, ['date']);

	$conn = $app['service.db'];
    $stats = array();

    $q = $conn->query("select * from stats.domain_stats order by name",
        array());
    foreach ($q as $row) {
        $stats[] = $row;
    }

    return $app->json(array('success' => true, 'stats' => $stats));

});

$app->get('/status/domain-isp-stats', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));
	$user = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $user['secret'], $req->get('signature'));

	$conn = $app['service.db'];
    $stats = array();

    $q = $conn->query("select stats.domain_isp_stats.*, tags.name, tags.description
        from stats.domain_isp_stats
        inner join tags on tags.id = domain_isp_stats.tag
        order by name",
        array());
    foreach ($q as $row) {
        $stats[] = $row;
    }

    return $app->json(array('success' => true, 'stats' => $stats));

});

$app->get('/status/isp-stats/{region}', function(Request $req, $region) use ($app) {
	function mkint($v) {
		return (int)$v;
	}

	checkParameters($req, array('email','signature','date'));
    $user = checkAuth($app, $req, ['date']);

	$conn = $app['service.db'];

	$rs = $conn->query("select isps.description, ok, blocked, timeout, error, dnsfail, total
	from isp_stats_cache
    inner join isps on isps.name = isp_stats_cache.network_name
    where regions && makearray(?)
	order by isps.description", array($region));

	$output = array();
    foreach ($rs as $row) {
		$net = $row['description'];
		unset($row['description']);
		$output[$net] = array_map("mkint", $row);
	}


	return $app->json(array('success' => true, 'isp-stats' => $output));
})->value('region', 'gb');

$app->get('/status/ispreport-stats', function (Request $req) use ($app) {
    checkParameters($req, array('email','signature','date'));
    $user = checkAuth($app, $req, ['date']);

    $conn = $app['service.db'];

    $q = $conn->query("select network_name, 
        count(*) as sent, 
        sum(unblocked) as total_unblocked,
        sum(case when not exists(
            select * from isp_reports r where r.urlid = isp_reports.urlid and r.id <> isp_reports.id and r.status = 'sent'
            ) then unblocked else 0 end
            ) as unblocked, 
        avg(case when unblocked = 1 and not exists(select * from isp_reports r where r.urlid = isp_reports.urlid and r.id <> isp_reports.id and r.status = 'sent')
            then last_updated - submitted else null end) as avg_unblock
        from isp_reports 
        where report_type = 'unblock' and status = 'sent' 
        group by network_name 
        order by network_name",
        array());

    $output = array();
    $ttl_time = 0;
    $ttl_sent = 0;
    $ttl_unblock = 0;
    foreach($q as $row) {
        $time = explode(" ", $row['avg_unblock'])[0];
        $output[ $row['network_name'] ] = array(
            'sent' => $row['sent'], 
            'unblocked' => $row['unblocked'],
            'avg_unblock_time' => $time
        );
        $ttl_time += $time * $row['unblocked'];
        $ttl_sent += $row['sent'];
        $ttl_unblock += $row['unblocked'];
    }
    $output['all'] = array(
        'sent' => $ttl_sent,
        'unblocked' => $ttl_unblock,
        'avg_unblock_time' => $ttl_time / $ttl_unblock
    );

	return $app->json(array('success' => true, 'unblock-stats' => $output));
});

$app->get('/status/country-stats', function(Request $req) use ($app) {
    checkParameters($req, array('email','signature','date'));
    $user = checkAuth($app, $req, ['date']);

    $conn = $app['service.db'];

    $rs = $conn->query("select unnest(regions) region, count(distinct urlid) blocked_url_count 
        from isps 
        inner join url_latest_status on network_name = isps.name 
        where regions && '{eu}' and blocktype = 'COPYRIGHT' and url_latest_status.status = 'blocked' 
        group by unnest(isps.regions);", array());
    
    $output = array();
    foreach($rs as $row) {
        $output[] = $row;
    }
    return $app->json(array(
        'success' => true,
        'stats' => $output
    ));

});

$app->get('/status/result/{uuid}', function (Request $req, $uuid) use ($app) {
    checkParameters($req, array('email','signature','date'));
    $user = checkAuth($app, $req, ['date']);

    $data = $app['service.dynamo']->get($uuid);

    return $app->json(array(
        'success' => true,
        'result' => $data
    ));
});

$app->get('/status/blocks/{region}', function(Request $req, $region) use ($app) {
    checkParameters($req, array('email','signature','date'));
    $user = checkAuth($app, $req, ['date']);

    $conn = $app['service.db'];
    $page = $req->get('page', 0);
    $off = (int)$page * 25;
    $rs = $conn->query("select count(*) ct, count(distinct urlid) urlcount 
        from url_latest_status uls 
        inner join urls using (urlid) 
        inner join isps on uls.network_name = isps.name
        where urls.status = 'ok' and blocktype='COPYRIGHT' and regions && makearray(?) and urls.url ~* '^https?://[^/]+$'",
        array($region)
    );
    $row = $rs->fetch();
    $count = $row['ct'];
    $urlcount = $row['urlcount'];
    $sortfield = $req->get('sort', 'url');

    if (!in_array($sortfield, array('url','last_blocked'))) {
        return $app->json(array('success'=>false, 'message'=>'invalid sort order'), 400);
    }
    if ($sortfield == 'last_blocked') {
        $sortfield = "last_blocked desc";
    }

    if ($req->get('format', 'networkrow') == 'networkrow') {
        /*
        $rs = $conn->query("select url, network_name, fmtime(uls.first_blocked) as first_blocked,
            fmtime(uls.last_blocked) as last_blocked
            from url_latest_status uls 
            inner join urls using (urlid)
            inner join isps on uls.network_name = isps.name
            where blocktype = 'COPYRIGHT'  and urls.status = 'ok' and regions && makearray(?) and urls.url ~* '^https?://[^/]+$'
            order by max(uls.first_blocked) over (partition by urlid) desc, urlid, uls.first_blocked desc
            offset $off limit 25", array($region));
         */
        $rs = $conn->query("select url, unnest(networks) as network_name, first_blocked , last_blocked
            from stats.cache_copyright_blocks cb
            where regions && makearray(?)
            order by first_blocked desc
            offset $off limit 25", array($region));

        $output = array();
        foreach($rs as $row) {
            $output[] = array(
                'url' => $row['url'],
                'first_blocked' => $row['first_blocked'],
                'last_blocked' => $row['last_blocked'],
                'network_name' => $row['network_name'],
            );
        }

    } elseif ($req->get('format','networkrow') == 'injunction') {
        $rs = $conn->query("select a.*, cjuf.reason, cjuf.abusetype from (
              select cj.name judgment_name, cj.date judgment_date, cj.url wiki_url, cj.judgment_url judgment_url, cj.citation citation, cj.sites_description judgment_sites_description, 
                    cjug.name url_group_name, 
                    urls.url, array_agg(network_name) as networks, fmtime(min(uls.first_blocked)) as first_blocked,
                    fmtime(max(uls.last_blocked)) as last_blocked,
                    cj.id as judgment_id, cju.id as urlid
                    from url_latest_status uls 
                    inner join urls using (urlid)
                    inner join isps on uls.network_name = isps.name and regions && makearray(?) 
                    left join frontend.court_judgment_urls cju on urls.url = cju.url 
                    left join frontend.court_judgments cj on cju.judgment_id = cj.id 
                    left join frontend.court_judgment_url_groups cjug on cjug.id = cju.group_id
                    where blocktype = 'COPYRIGHT'  and urls.status = 'ok'  and urls.url ~* '^https?://[^/]+$'
                    group by cj.id, cj.date, cj.sites_description, cj.name, cj.url, cj.judgment_url, cj.case_number, cjug.id, cjug.name, urls.url, cju.id
              UNION
              select cj.name judgment_name, cj.date judgment_date, cj.url wiki_url, cj.judgment_url judgment_url, cj.citation citation, cj.sites_description judgment_sites_description, 
                    null, null, null, null, null,
                    cj.id as judgment_id, null
                    FROM frontend.court_judgments cj
                    left join frontend.court_judgment_urls cju on (cj.id = cju.judgment_id)
                    where cju.id is null or cju.judgment_id is null
              
              ) a 
              left join frontend.court_judgment_url_flags cjuf on a.urlid = cjuf.judgment_url_id
              order by judgment_date desc nulls last, judgment_name nulls last, url_group_name nulls last, url 
              offset $off limit 25",
              array($region));

        $output = array();
        foreach($rs as $row) {
            $output[] = array(
                'judgment_name' => $row['judgment_name'],
                'judgment_date' => $row['judgment_date'],
                'judgment_url' => $row['judgment_url'],
                'citation' => $row['citation'],
                'wiki_url' => $row['wiki_url'],
                'judgment_sites_description' => $row['judgment_sites_description'],
                'url_group_name' => $row['url_group_name'],
                'url' => $row['url'],
                'first_blocked' => $row['first_blocked'],
                'last_blocked' => $row['last_blocked'],
                'networks' => explode(",", substr($row['networks'], 1, -1)),
                'error_status' => $row['reason'],
                'additional_error_information' => $row['abusetype']
            );
        }

    } else {
        /*$rs = $conn->query("select url, array_agg(network_name) as networks, fmtime(min(uls.first_blocked)) as first_blocked,
            fmtime(max(uls.last_blocked)) as last_blocked
            from url_latest_status uls 
            inner join urls using (urlid)
            inner join isps on uls.network_name = isps.name and regions && makearray(?) and urls.url ~* '^https?://[^/]+$'
            where blocktype = 'COPYRIGHT'  and urls.status = 'ok' 
            group by url
            order by $sortfield
           offset $off limit 25", array($region));*/
       $rs = $conn->query("select url, networks, first_blocked, last_blocked from stats.cache_copyright_blocks where regions && makearray(?) order by url offset $off limit 25",
               array($region));

        $output = array();
        foreach($rs as $row) {
            $output[] = array(
                'url' => $row['url'],
                'first_blocked' => $row['first_blocked'],
                'last_blocked' => $row['last_blocked'],
                'networks' => explode(",", substr($row['networks'], 1, -1)),
            );
        }
    }

    return $app->json(array(
        'success' => true,
        'count' => $count,
        'urlcount' => $urlcount,
        'results' => $output
    ));
})->value('region', 'gb');

$app->get('/status/ispreports', function (Request $req) use ($app) {
    $user = checkAuth($app, $req, ['date']);

    $filter = array();

    foreach(array('open','sent','unresolved','reviewed','cancelled','featured','egregious','harmless','resubmit',
        'accepted', 'rejected', 'not_accepted','hold') as $ftype) {
        if ($req->get($ftype,null)) {
            $filter['state'] = $ftype;
            break;
        }
    }

    $filter['url'] = $req->get('url',null);
    $filter['network'] = $req->get('isp',null);
    $filter['category'] = $req->get('category',null);
    $filter['reportercategory'] = $req->get('reportercategory', null);
    $filter['list'] = $req->get('list',null);
    $filter['user'] = $req->get('user',null);
    $filter['age'] = $req->get('age', null);
    $filter['url_status'] = $req->get('url_status',null);
    $filter['order'] = $req->get('order', 'desc');
    if (!in_array($filter['order'], array('asc','desc'))) {
        throw new InvalidSortError();
    }
    $filter['policy'] = $req->get('policy', null);
    if (!is_null($filter['policy'])) {
        // convert to bool
        if ($filter['policy'] == "unknown") {
            $filter['policy'] = "unknown";
        } else {
            $filter['policy'] = in_array($filter['policy'], array("false", "False", "0", "f", 0)) ? false : true;
        }
    }
    $filter['year'] = $req->get('year', null);
    $filter['report_type'] = $req->get('report_type');

    $page = $req->get('page', 0);
    $is_admin = ($user['administrator'] == 1 && $req->get('admin') == 1) ? 1 : 0;

    $output = array();
    $output['count']          = $app['db.ispreport.load']->count_reports('unblock', $filter, $is_admin);
    $output['open_count']     = $app['db.ispreport.load']->count_reports('unblock', array_merge($filter, array('state' => 'open')), $is_admin);
    $output['hold_count']     = $app['db.ispreport.load']->count_reports('unblock', array_merge($filter, array('state' => 'hold')), $is_admin);
    $output['review_count']   = $app['db.ispreport.load']->count_reports('unblock', array_merge($filter, array('state' => 'reviewed')), $is_admin);
    $output['feature_count']  = $app['db.ispreport.load']->count_reports('unblock', array_merge($filter, array('state' => 'featured')), $is_admin);
    $output['cancel_count']   = $app['db.ispreport.load']->count_reports('unblock', array_merge($filter, array('state' => 'cancelled')), $is_admin);
    $output['harmless_count'] = $app['db.ispreport.load']->count_reports('unblock', array_merge($filter, array('state' => 'harmless')), $is_admin);
    $output['resubmit_count'] = $app['db.ispreport.load']->count_reports('unblock', array_merge($filter, array('state' => 'resubmit')), $is_admin);
    
    $output['accepted_count']     = $app['db.ispreport.load']->count_reports('unblock', array_merge($filter, array('state' => 'accepted')), $is_admin);
    $output['not_accepted_count'] = $app['db.ispreport.load']->count_reports('unblock', array_merge($filter, array('state' => 'not_accepted')), $is_admin);
    $output['rejected_count']     = $app['db.ispreport.load']->count_reports('unblock', array_merge($filter, array('state' => 'rejected')), $is_admin);

    $reports = $app['db.ispreport.load']->get_reports('unblock', $filter, $page,  $is_admin);

    $output['success'] = true;
    $output['reports'] = $reports;
    if ($filter['network']) {
        $output['isp'] = $filter['network'];
    }

    return $app->json($output);
});


class StreamResultProcessor {
	function __construct($conn) {
		$this->conn = $conn;
	}


	function result_callback($msg, $queue) {
		#print $msg->getBody() . "\n";
		$data =(array)json_decode($msg->getBody());

	# Fetch results from status summary table, left joining to get last blocked time
	$result = $this->conn->query("select isps.description, l.status,
        fmtime(l.created) created, fmtime(l.last_blocked) last_blocked,
        fmtime(l.first_blocked) first_blocked, l.category
		from url_latest_status l
		inner join isps on isps.name = l.network_name
		inner join urls on urls.urlID = l.urlID
		where urls.url = ? and l.network_name = ?",
		array($data['url'], $data['network_name']));

		$row = $result->fetch(PDO::FETCH_NUM);

		$data['network_name'] = $row[0];
		$data['status_timestamp'] = $row[2];
		$data['last_blocked_timestamp'] = $row[3];
		$data['first_blocked_timestamp'] = $row[4];
		$data['category'] = $row[5];

		print json_encode($data) . "\n\n";

		ob_flush();
		$queue->ack($msg->getDeliveryTag());
	}
}

$app->get('/stream/results/{region}', function (Request $req, $region) use ($app) {
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
    $user = checkAuth($app, $req, ['url','date']);

	$timeout = (int)$req->get('timeout');
	if ($timeout) {
		if (!(5 <= $timeout && $timeout <= 30)) {
			return $app->json(array(
				"success" => false,
				"error" => "Invalid timeout value"
			), 400);
		}
	} else {
		$timeout = 15;
	}
	debug_log("Timeout set to: $timeout");

	list($amqpconn, $ch) = amqp_connect_full();
	$amqpconn->setTimeout($timeout);

	$url = normalize_url($req->get('url'));
	$hash = md5($url);

	try {
		$q = new AMQPQueue($ch);
		$q->setName("result." . $hash);

		$q->setFlags(AMQP_AUTODELETE|AMQP_EXCLUSIVE);
		$q->declare();
		$q->bind("org.results", "results.*." . $hash . '.*');


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
		# Fetch results from status summary table
		$result = $conn->query("select isps.description, l.status,
        fmtime(l.created) created,
        fmtime(l.last_blocked) last_blocked,
        fmtime(l.first_blocked) first_blocked,
        l.category
		from url_latest_status l
		inner join urls on urls.urlID = l.urlID
		inner join isps on isps.name = l.network_name
		where urls.url = ? and isps.show_results = 1 and regions && makearray(?)",
		array($url, $region),
        PDO::FETCH_NUM
        );

        foreach ($result as $row) {
			$out = array(
                'network_name' => $row[0],

			# get latest status and result

                'status' =>  $row[1],
                'status_timestamp' =>  $row[2],
                'last_blocked_timestamp' =>  $row[3],
                'first_blocked_timestamp' =>  $row[4],
                'category' =>  $row[5]
                );

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

})->value('region','gb');

$app->post('/verify/email', function (Request $req) use ($app) {
	#checkParameters($req, array('email','signature','token','date'));
    $user = checkAuth($app, $req, ['token','date']);

	$conn = $app['service.db'];
    $token = $req->get('token');
	if (substr($token, 0, 1) == 'A') {
		# URL subscription token

		$conn->beginTransaction();
		try {
			$result = $conn->query("select contactID from url_subscriptions
				where token = ?",
				array($req->get('token'))
			);
			$row = $result->fetch(PDO::FETCH_NUM);
			if (!$row) {
				throw new TokenLookupError();
			}
			$conn->query("update contacts set verified = 1 where id = ?",
				array($row[0])
			);
			$result = $conn->query("update url_subscriptions set verified = 1, token = null
				where verified = 0 and token = ?",
				array($token)
			);

			if ($result->rowCount() != 1) {
				throw new TokenLookupError();
			}
			$conn->commit();
		} catch (Exception $err) {
			error_log("Rolling back");
			$conn->rollback();
			throw $err;
		}
    } elseif (substr($token, 0, 1) == 'B') {
        // verifying user after ISP report submit
        try {
            $conn->beginTransaction();
            $contact = $app['db.contact.load']->loadByToken($token);
            $result = $conn->query("update contacts set verified = 1, token = null
                where verified = 0 and token = ?",
                array($token)
                );
            if ($result->rowCount() != 1) {
                throw new TokenLookupError();
            }

            // now get pending reports

            $res = $conn->query("select * from isp_reports
                where contact_id = ? and (status = 'sent' or status = 'new')", # should be 'new', transitioning
                array($contact['id'])
                );
            foreach ($res as $row) {
                if ($row['status'] == 'sent') {
                    // previously reports for unverified users were set to status 'sent'
                    // this block takes care of those.

                    $network = $app['db.isp.load']->load($row['network_name']);
                    $url = $app['db.url.load']->loadByID($row['urlid']);

                    sendISPReport(
                        $row['mailname'],
                        $row['name'],
                        $row['email'],
                        $network,
                        $url['url'],
                        $row['message'],
                        $row['report_type'],
                        $row['site_category'],
                        $app['service.template']
                    );
                }
                if ($row['status'] == 'new') {
                    // now reports for unverified users are set to status 'new', so we set the status for
                    // requeue to send
                    $app['db.ispreport.load']->set_status($row['id'], 'pending');
                }
            }

            $conn->commit();

        } catch (Exception $err) {

			error_log("Rolling back");
			$conn->rollback();
			throw $err;
		}


	} else {
		throw new InvalidTokenError();
	}

	return $app->json(array('success' => true, 'verified' => true));

});

#-----------
# DMOZ category functions
#-----------
$app->get('/category/search', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','search'));
    $search = $req->get('search');
    $user = checkAuth($app, $req, ['search'], false, false);

    $output = array('success' => true, 'categories' => array());

    #foreach($app['db.category.load']->search($search, 20) as $row) {
    #    $output['categories'][] = $row;
    #}

    $data = $app['service.elastic']->query($search . "*", '/categories',
        array(
            array('total_blocked_url_count' => 'desc')
            )
        );
    foreach($data->results as $src) {
        $output['categories'][] = array(
            'id' => $src->id,
            'display_name' => implode('/', $src->display_name),
            'name' => $src->name,
            'total_blocked_url_count' => $src->total_blocked_url_count
            );
    }

    return $app->json($output);

});

$app->get('/category/random', function (Request $req) use ($app) {
    checkParameters($req, array('email','signature'));
    $user = checkAuth($app, $req, []);

    $output = array('success' => true);

    /*
    $q = $app['db.category.load']->random(1);
    $row = $q->fetchone();
    */

    $row = $app['service.redis.cache']->lPop("randomcat");

    $output['id'] = $row['id'];
    $output['name'] = $row['name'];

    return $app->json($output);


});

$app->get('/category/{parent}', function(Request $req, $parent) use ($app) {

	checkParameters($req, array('email','signature'));
    $user = checkAuth($app, $req, $parent, false, false);

    $show_empty = $req->get('show_empty', 1);
    $sort = $req->get('sort', 'display_name');

    $cached = null; #$app['service.redis.cache']->get("cat:$parent");

    if ($cached) {
        // cache whole output - may require locking under high traffic
        $output = $cached;
    } else {

        $output = array('success' => true);
        if ($parent != "0") {
            $cat1 = $app['db.category.load']->load($parent);
            if (!$cat1) {
                return $app->json(array("status"=>"notfound"),404);
            }
            $output['id'] = $parent;
            $output['name'] = $cat1['display_name'];
            $res = $app['db.category.load']->load_children($cat1, $show_empty, $sort);

            $output['parent'] = $app['db.category.load']->get_parent($cat1);
            $output['parents'] = $app['db.category.load']->get_parents($cat1);

            $output['blocked_url_count'] = $cat1['blocked_url_count'];
            $output['block_count'] = $cat1['block_count'];
            $output['total_blocked_url_count'] = $cat1['total_blocked_url_count'];
            $output['total_block_count'] = $cat1['total_block_count'];
        } else {
            $res = $app['db.category.load']->load_toplevel($show_empty, $sort);
        }

        $cat = array();
        foreach ($res as $row) {
            $cat[] = array(
                'id' => $row['id'],
                'fullname' => $row['display_name'],
                'name' => $row['name'],
                'total_block_count' => $row['total_block_count'],
                'total_blocked_url_count' => $row['total_blocked_url_count'],
                'block_count' => $row['block_count'],
                'blocked_url_count' => $row['blocked_url_count']
            );
        }
        $output['categories'] = $cat;

        $app['service.redis.cache']->set("cat:$parent", $output);
    }

    return $app->json($output);


})->value('parent',0);


$app->get('/category/sites/{parent}', function (Request $req, $parent) use ($app) {

	checkParameters($req, array('email','signature'));
    $user = checkAuth($app, $req, $parent, false, false);

    $cat = $app['db.category.load']->load($parent);
    if (!$cat) {
        return $app->json(array("status"=>"notfound"),404);
    }

    $sites = array();
    if (defined('CATEGORY_SEARCH_ELASTIC')) {
        $result = $app['service.elastic']->urls_by_category($parent, $req->get('page', 0));

        foreach($result->results as $hit) {
            $sites[] = $app['db.category.load']->load_block($hit->id, $req->get('active', 0));
        }
    } else {

    if ($req->get('recurse') && $parent) {
        $res = $app['db.category.load']->load_blocks_recurse($parent, $req->get('page', 0), $req->get('active', 0), 20);
    } else {
        $res = $app['db.category.load']->load_blocks($parent, $req->get('active', 0));
    }
    foreach ($res as $data) {
        $sites[] = $data;
    }

    }

    return $app->json(array(
        "success" => true,
        "id" => $parent,
        "category" => $cat['display_name'],
        "name" => $cat['name'],
        "total_blocked_url_count" => $cat['total_blocked_url_count'],
        "total_block_count" => $cat['total_block_count'],
        "sites"=> $sites));
});

#------------
# END DMOZ category functions
#------------

#------------
# Site reporting functions
#------------

$app->get('/ispreport/candidates', function (Request $req) use ($app) {
    #$data = $app['db.url.load']->get_unreported_blocks($req->get('count',10));

    $redis = $app['service.redis.cache'];
    $data = array();
    for ($i = 0; $i < $req->get('count',10); $i++) {
        $url = $redis->lPop('randomlinks');
        if (!$url) {
            break;
        }
        $data[] = $url;
    }


    return $app->json(array(
        'success' => true,
        'status' => 'blocked',
        'results' => $data
        ), 200 );
});

$app->post('/ispreport/submit', function (Request $req) use ($app) {
    global $CORS_HEADERS;
    /*
    Accepts a JSON post body with this structure:
    {
      'url': 'http://www.example.com',
      'networks': ["O2","Vodafone"],
      'reporter': {
        'name': "J Bloggs",
        'email': 'j.bloggs@example.com'
      },
      'message': "I would like this unblocked because ...",
      'category': 'gambling',
      'auth': {
        'email': 'useraccount@blocked.org.uk',
        'signature': 'abcdef0123456',
      }
    }
    */

    $conn = $app['service.db'];
    $data = (array)json_decode($req->getContent(), true);
    debug_log("POST: " . print_r($data, true));

    # TODO
	$user = $app['db.user.load']->load($data['auth']['email']);
    Middleware::checkMessageTimestamp($data['date']);
    Middleware::verifyUserMessage(
        $data['url'] . ":" . $data['date'],
        $user['secret'],
        $data['auth']['signature']
        );

    $url = $app['db.url.load']->load(normalize_url($data['url']));

    if (!(@count($data['networks']) == 1 && ($data['networks'][0] == 'ORG' || $data['networks'][0] == "BBFC"))) {
        // we are submittingt to ISPs, not feedback to ORG or BBFC
        if ($app['db.blacklist.load']->check($url['url'])) {
            debug_log("{$url['url']} cannot be submitted");
            return $app->json(array('success' => false, 'message' => 'domain rejected'));
        }
    }

    $app['db.contact.load']->validateEmail($data['reporter']['email']);
    
    $contact = $app['db.contact.load']->insert(
        $data['reporter']['email'],
        $data['reporter']['name'],
        false
        );

    if (!$contact['enabled']) {
        throw new ContactPermissionDeniedError();
    }

    try {
        $app['service.queue']->submitted_url($url['url']);
    } catch (Exception $exc) {
        error_log("Error posting submission to queue: " . $exc);
    }

    if (!isset($data['networks']) || count($data['networks']) == 0) {
        $data['networks'] = $app['db.ispreport.load']->get_unreported($url['urlid']);
        debug_log("Unreported: " . implode(",", $data['networks']));
    }

    if (!$contact['verified'] && !(count($data['networks']) == 1 && ($data['networks'][0] == 'ORG' || $data['networks'][0] == 'BBFC') )) {
        // reports sent to ORG or BBFC only are exempt from validation
        if (!$contact['token']) {
            $token = "B" . md5($contact['id'] . "-" .
                Middleware::generateSharedSecret(10));

            $conn->query("update contacts set
                token = ?, verify_attempts=1, verify_last_attempt=now()  where id = ?",
                array( $token, $contact['id'])
                );

            sendUserVerification($data['reporter']['email'], $data['reporter']['name'], $token, 1, $app['service.template']);
        }

    }

    $ids = array();
    $queued = array();
    $rejected = array();

    $hold = $app['db.url.load']->has_hold_category($url['urlid']);
    debug_log("Hold: $hold");
    foreach($data['networks'] as $network_name) {
        debug_log("Looking up: ". $network_name);
        $age_limit = false;
        $network = $app['db.isp.load']->load($network_name);

        // check latest status
        // special case for the pseudo-isp ORG
        if ($network_name != "ORG" && $network_name != "BBFC") {
            $q = $app['service.db']->query("select id, (now()-created) > interval '14 days' as age_limit
                from url_latest_status
                where urlID = ? and network_name = ? and status = 'blocked'",
                array($url['urlid'], $network_name)
                );
            $row = $q->fetch(PDO::FETCH_NUM);
            if (!$row) {
                $rejected[$network_name] = "Not blocked on this network";
                continue;
            }
            if ($row[1] == 't') {
                $age_limit = 't';
                debug_log("Age limited");
                $queued[] = $network_name;
            }
        }
        if (!$network['admin_email']) {
            $rejected[$network_name] = "No administration email for this network";
            continue;
        }

        if ($hold) {
            $queued[] = $network_name;
        }

        if ($app['db.ispreport.load']->can_report($url['urlid'], $network_name)) {

            // don't get smart with ternary expressions here
            if ($hold) {
                $status = 'hold';
            } elseif ($age_limit) {
                $status = 'pending';
            } elseif ($contact['verified'] == '1') {
                $status = 'sent';
            } else {
                $status = 'new';
            }

            debug_log("Status: $status $hold $age_limit {$contact['verified']}");
            $mailname = "reply-" . strtolower(Middleware::generateSharedSecret(12));
            $ids[$network_name] = $app['db.ispreport.load']->insert(
                $mailname,
                $data['reporter']['name'],
                $data['reporter']['email'],
                $url['urlid'],
                $network_name,
                $data['message'],
                $data['report_type'],
                (@$data['send_updates'] ? 1 : 0),
                $contact['id'],
                (@$data['allow_publish'] ? 1: 0),
                $status,
                @$data['category'],
                (@$data['allow_contact'] ? 1: 0),
                @$data['usertype'] ? $data['usertype'] : null
                );
            # send email here

            if (($contact['verified'] || $network_name == 'ORG') && $network_name != 'BBFC' && $age_limit == false && $hold == false) {
                sendISPReport(
                    $mailname,
                    $data['reporter']['name'],
                    $data['reporter']['email'],
                    $network,
                    $url['url'],
                    $data['message'],
                    $data['report_type'],
                    $data['category'],
                    $app['service.template']
                    );
            }
            if ($network_name == "BBFC") {
                sendBBFCReport($mailname,
                               $data['reporter']['name'],
                               $data['reporter']['email'],
                               $network,
                               $data['original_network'],
                               $url['url'],
                               $data['message'],
                               $data['additional_contact'],
                               $app['service.template']
                               );
            }


        } else {
            $rejected[$network_name] = "Already reported";
        }

    }
    if (count($queued) > 0) {
        $app['db.url.load']->updateLastPolled($url['urlid']);
        $app['service.queue']->publish_url($url['url']);
    }

    return $app->json(array(
        'success' => true,
        'verification_required' => ($contact['verified']) ? false : true,
        'verification_pending' => (!$contact['verified'] && $contact['token']) ? true : false,
        'report_ids' => $ids,
        'rejected' => $rejected,
        'queued' => $queued
    ), 201);



});


$app->run();

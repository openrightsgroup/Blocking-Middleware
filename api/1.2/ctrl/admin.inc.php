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

# flag a report as abuse

$app->post('/ispreport/flag', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date') . ":" . $req->get('url'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);

    $urldata = $app['db.url.load']->load($req->get('url'));
    
    $loader = $app['db.ispreport.load'];
    $result = $loader->flag($urldata['urlid'], $req->get('status','abuse'));

    return $app->json(array('success' => true, 'url' => $req->get('url'), 'updated' => $result));

});

$app->post('/ispreport/unflag', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date') . ":" . $req->get('url'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);

    $urldata = $app['db.url.load']->load($req->get('url'));
    
    $loader = $app['db.ispreport.load'];
    $result = $loader->unflag($urldata['urlid']);

    return $app->json(array('success' => true, 'url' => $req->get('url'), 'updated' => $result));

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

$app->get('/courtorders', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);

    $loader = $app['db.courtorder.load'];
    $orders = $loader->select();
    
    return $app->json(array('success' => true, 'courtorders' => $orders));

});

$app->get('/courtorders/{name}', function(Request $req, $name) use ($app) {
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);

    $loader = $app['db.courtorder.load'];
    $order = $loader->load($name);

    $urls = $loader->get_urls($order['id']);
    $isp_info = $loader->get_network_urls($order['id']);
    return $app->json(array('success' => true, 'courtorder' => $order, 'urls' => $urls, 'isp_urls' => $isp_info));

});

$app->post('/courtorders/{name}', function(Request $req, $name) use ($app) {
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);

    $loader = $app['db.courtorder.load'];
    $loader->update($name, 
        $req->get('name'), $req->get('order_date'), $req->get('url'), 
        $req->get('judgment'), $req->get('judgment_date'), $req->get('judgment_url')
    );
    $order = $loader->load($req->get('name'));

    return $app->json(array('success' => true, 'courtorder' => $order));

});

$app->post('/courtorders', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);

    $loader = $app['db.courtorder.load'];
    $loader->insert(
        $req->get('name'), $req->get('order_date'), $req->get('url'), 
        $req->get('judgment'), $req->get('judgment_date'), $req->get('judgment_url')
    );

    return $app->json(array('success' => true, 'courtorder' => $req->get('name')));
});

$app->delete('/courtorders', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);

    $loader = $app['db.courtorder.load'];
    $loader->delete($req->get('name'));

    return $app->json(array('success' => true, 'courtorder' => $req->get('name')));
});


$app->post('/courtorders/isp_urls', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);

    $order = $app['db.courtorder.load']->load($req->get('name'));
	$isp = $app['db.isp.load']->load($req->get('network_name'));

    $app['db.courtorder.load']->add_network_url($order['id'], $isp['id'], $req->get('url'));

    return $app->json(array('success' => true, 'courtorder' => $order['name']));
});

$app->delete('/courtorders/isp_urls', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);

    $order = $app['db.courtorder.load']->load($req->get('name'));
	$isp = $app['db.isp.load']->load($req->get('network_name'));

    $app['db.courtorder.load']->delete_network_url($order['id'], $isp['id']);

    return $app->json(array('success' => true, 'courtorder' => $order['name']));
});

$app->post('/courtorders/sites', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);

    $loader = $app['db.courtorder.load'];
    $urlloader = $app['db.url.load'];

    $order = $loader->load($req->get('name'));
    $urldata = $urlloader->load($req->get('url'));

    $loader->add_url($order['id'], $urldata['urlid']);

    return $app->json(array('success' => true, 'courtorder' => $req->get('name'), 'url' => $req->get('url')));
});

$app->delete('/courtorders/sites', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $adminuser['secret'], $req->get('signature'));
	checkAdministrator($adminuser);

    $loader = $app['db.courtorder.load'];
    $urlloader = $app['db.url.load'];

    $order = $loader->load($req->get('name'));
    $urldata = $urlloader->load($req->get('url'));

    $loader->delete_url($order['id'], $urldata['urlid']);

    return $app->json(array('success' => true, 'courtorder' => $req->get('name'), 'url' => $req->get('url')));
});

/*  -------^---^---^---- End Administrator functions ... */


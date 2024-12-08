<?php

include "../backend/silex/vendor/autoload.php";

$loader = new Twig_Loader_Filesystem("../api/1.2/templates");
$twig = new Twig_Environment($loader, array(
	'cache' => false,
	'debug' => true,
	'autoescape' => false
));
$twig->addExtension(new Twig_Extension_Debug());

print "report_bbfc\n===========\n";

print $twig->render('report_bbfc.txt', 
	array('bar' => 'bar',
		'url' => 'http://example.com',
		'original_network' => 'three',
		'reporter_name' => 'bob',
		'reporter_email' => 'admin@1.com',
		'submission' => array(),
		'message' => 'foo', 
		'previous_message' => 'bar'

)
);

print "report_email\n===========\n";

print $twig->render('report_email.txt', 
	array(
		'url' => 'http://example.com',
		'original_network' => 'three',
		'reporter_name' => 'bob',
		'reporter_email' => 'admin@1.com',
		'submission' => array(),
		'message' => 'foo', 
		'previous_message' => 'bar'

)
);

print "report_reminder\n===========\n";

print $twig->render('report_reminder.txt', 
	array(
		'url' => 'http://example.com',
		'original_network' => 'three',
		'reporter_name' => 'bob',
		'reporter_email' => 'admin@1.com',
		'submission' => array(),
		'message' => 'foo', 
		'previous_message' => 'bar'

)
);

print "subscribe_email\n===========\n";

print $twig->render('subscribe_email.txt', 
	array(
        'name' => 'Bob',
        'confirm_url' => 'http://example.com',
        'token' => '123123',
        'site_url' => 'www.blocked.org.uk',
        'site_email' => 'blocked@openrightsgroup.org',
        'site_name' => 'Blocked'


)
);

print "verify_email\n===========\n";

print $twig->render('verify_email.txt', 
	array(
        'name' => 'Bob',
        'confirm_url' => 'http://example.com',
        'token' => '123123',
        'site_url' => 'www.blocked.org.uk',
        'site_email' => 'blocked@openrightsgroup.org',
        'site_name' => 'Blocked'


)
);


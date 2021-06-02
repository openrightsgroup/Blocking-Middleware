<?php

/* Tests for the url library */

include "../api/1.2/libs/url.php";

 //one of these days I'll use phpunit

$FAIL = 0;

function compare($input, $expect) {
    global $FAIL;

    $output = normalize_url($input);
    if ($output != $expect) {
        $FAIL ++ ;
        print "FAIL: $input => $output ($expect)\n";
    } else {
        print "  OK: $input => $output ($expect)\n";
    }
}

compare("Http://reddit.com", "http://reddit.com");
compare("http://reddit.com/", "http://reddit.com");
compare("https://reddit.com", "https://reddit.com");
compare("reddit.com", "http://reddit.com");
compare("REDDIT.COM", "http://reddit.com");
compare("reddit.com/r/nsfw", "http://reddit.com/r/nsfw");
compare("Http://reddit.com/r/GoneWild", "http://reddit.com/r/GoneWild");
compare("HTTP://WWW.REDDIT.COM", "http://www.reddit.com");
compare("HTTP://WWW.REDDIT.COM/R/NSFW", "http://www.reddit.com/R/NSFW");
compare("HTTPS://WWW.REDDIT.COM/R/NSFW", "https://www.reddit.com/R/NSFW");

function compare2($input, $expect) {
    global $FAIL;

    $output = preferred_domain_url($input);
    if ($output != $expect) {
        $FAIL = 1;
        print "FAIL: $input => $output ($expect)\n";
    } else {
        print "  OK: $input => $output ($expect)\n";
    }
}

compare2("https://www.reddit.com", "http://reddit.com");
compare2("http://www.reddit.com", "http://reddit.com");
compare2("http://ww2.reddit.com", "http://reddit.com");
compare2("https://reddit.com", "http://reddit.com");
compare2("http://reddit.com", "http://reddit.com");
compare2("HTTPS://WWW.REDDIT.COM", "http://reddit.com");
compare2("http://reddit.com", "http://reddit.com");

function compare3($input, $expect) {
    global $FAIL;
    $output = categorize_url($input);
    if ($output != $expect) {
        $FAIL = 1;
    }
    if ($output != $expect) {
        print "FAIL: $input => $output ($expect)\n";
    } else {
        print "  OK: $input => $output ($expect)\n";
    }
}

compare3('http://www.tld.com', 'SUBDOMAIN');
compare3('http://ww2.tld.com', 'SUBDOMAIN');
compare3('http://www.tld.co.uk', 'SUBDOMAIN');
compare3('http://ww2.tld.co.uk', 'SUBDOMAIN');
compare3('http://tld.co.uk', 'DOMAIN');
compare3('http://arthur.uk.com', 'SUBDOMAIN');
compare3('http://arthur.test', 'DOMAIN');
compare3('http://www.arthur.test', 'SUBDOMAIN');

function compare4($input, $expect) {
    global $FAIL;

    $output = preferred_domain_url($input);
    if ($output != $expect) {
        $FAIL = 1;
    }
    if ($output != $expect) {
        print "FAIL: $input => $output ($expect)\n";
    } else {
        print "  OK: $input => $output ($expect)\n";
    }
}

compare4("http://www.reddit.com", "http://reddit.com");
compare4("https://www.reddit.com", "http://reddit.com");
compare4("https://WWW.REDDIT.COM", "http://reddit.com");
compare4("http://www.bbc.co.uk/news", "http://bbc.co.uk");

if ($FAIL) {
    exit(1);
}


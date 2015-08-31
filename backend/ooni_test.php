<?php

define('OONI_TESTLIB_VERSION', 2015082901);

function find_dirty($entry_data) {
	foreach ($entry_data['requests'] as $request) {
        if (@$request['failure']) {
            continue;
        }
		if ($request['request']['tor']['is_tor'] == false) {
            return $request;
		}
	}
}
function find_tor($entry_data) {
	foreach ($entry_data['requests'] as $request) {
        if (@$request['failure']) {
            continue;
        }
		if ($request['request']['tor']['is_tor'] == true) {
            return $request;
		}
	}
}

function find_header($req, $hdrname) {
    foreach ($req['response']['headers'] as $hdr) {
        if ($hdr[0] == $hdrname) {
            return $hdr[1][0];
        }
    }
    return null;
}


function test_result($result) {
	
	if (!is_null($result['control_failure']) || !is_null($result['experiment_failure'])) {
		return array('error', null);
	}
    $tor = find_tor($result);
    $dirty = find_dirty($result);

    if (is_null($tor) || is_null($dirty)) {
        return array("error",null);
    }

    if ($tor['response']['code'] == 403 && $dirty['response']['code'] != 403) {
        $cat = '';
        if (find_header($tor, "Server") == 'cloudflare-nginx') {
            $cat = 'cloudflare-tor-block';
        }
        return array('unknown', $cat);
    }
    if ($tor['response']['code'] != 403 && $dirty['response']['code'] == 403) {
        $cat = '';
        if (find_header($dirty, "Server") == 'cloudflare-nginx') {
            $cat = 'cloudflare-client-block';
        }
        return array('blocked', $cat);
    }
    if (!is_null($result['body_length_match'])) {
        if (!$result['body_length_match']) {
            return array('blocked', null);
        }
    }
	return array('ok', null);

}

<?php

function find_dirty($entry_data) {
	foreach ($entry_data['requests'] as $request) {
		if ($request['request']['tor']['is_tor'] == false) {
            return $request;
		}
	}
}
function find_tor($entry_data) {
	foreach ($entry_data['requests'] as $request) {
		if ($request['request']['tor']['is_tor'] == true) {
            return $request;
		}
	}
}


function test_result($result) {
	
	if (!is_null($result['control_failure']) || !is_null($result['experiment_failure'])) {
		return 'error';
	}
    $tor = find_tor($result);
    if ($tor['response']['code'] == 403) {
        $dirty = find_dirty($result);
        if ($dirty['response']['code'] != 403) {
            return 'unknown';
        }
    }
    if (!is_null($result['body_length_match'])) {
        if (!$result['body_length_match']) {
            return 'blocked';
        }
    }
	return 'ok';

}

<?php

include_once __DIR__ . "/../api/1.2/libs/DB.php";

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$result = $conn->query("select last_id, last_checked from modx_copy",
	array());
$row = $result->fetch_assoc();

if ($row == null) {
	$last_id = 0;
} else {
	$last_id = $row['last_id'];
}

# this is fine while the two databases are on the same server
$result = $conn->query("select * from modx.modx_formsave_forms 	
	where id > ? order by id",
	array($last_id));

while ($row = $result->fetch_assoc()) {
	$data = (array)json_decode($row['data']);
	$id = $row['id'];

	print "Form: $id, {$data['domainToCheck']}\n";
	$conn->query("insert ignore into urls (url, hash, source, inserted)
		values (?,?,'user', now())",
		array($data['domainToCheck'], md5($data['domainToCheck'])));

	$insert_id = $conn->insert_id;
	if (!$insert_id) {
		// if insert ignore kicked in, we need to look up the urlid by hand
		$urlresult = $conn->query("select urlID from urls where url = ?",
		array($data['domainToCheck'])
		);
		$urlrow = $urlresult->fetch_assoc();
		$insert_id = $urlrow['urlID'];
	}

	$conn->query("insert into requests(urlid, userid, submission_info, created)
		values (?,-1,?,now())",
		array($insert_id, $row['data'])
		);
}

if($id) {
	// check that we've actually fetched some stuff

	$conn->query("insert into modx_copy(id, last_id, last_checked) 
		values(1, ?,now()) on duplicate key 
		update last_id=values(last_id), last_checked=now()",
		array($id)
		);
}

$conn->commit();

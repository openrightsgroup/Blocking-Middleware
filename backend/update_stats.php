<?php

include_once __DIR__ . "/../api/1.2/libs/DB.php";
$conn = db_connect();

if(count($argv) == 1) {
	echo "Required arg: <counters|isps|block-category>\n";
	exit(1);
}

if ($argv[1] == 'counters') {

	$result = $conn->query(" select count(*) from urls where not (source = 'dmoz' and lastPolled is null)", array());
	$row = $result->fetch(PDO::FETCH_NUM);

	$stats = array(
		'urls_reported' => $row[0],
		);

	$result = $conn->query("select count(distinct urlid) from url_latest_status",array());
	$row = $result->fetch(PDO::FETCH_NUM);
	$stats['urls_tested'] = $row[0];

	$result = $conn->query("select count(distinct urlid) from url_latest_status inner join urls using (urlid) 
        where source = 'alexa'",
        array()
        );
	$row = $result->fetch(PDO::FETCH_NUM);
	$stats['blocked_sites_sample_size'] = $row[0];

	$result = $conn->query("select count(distinct urlid) from url_latest_status inner join urls using (urlid) 
        where url_latest_status.status = 'blocked' and source='alexa'", 
        array()
        );
	$row = $result->fetch(PDO::FETCH_NUM);
	$stats['blocked_sites_detected'] = $row[0];

	$result = $conn->query("select count(distinct urlid) from url_latest_status 
        inner join isps on isps.name = url_latest_status.network_name
        inner join urls using (urlid) 
        where url_latest_status.status = 'blocked' and filter_level in ('','default') and source='alexa'",
        array()
        );
	$row = $result->fetch(PDO::FETCH_NUM);
	$stats['blocked_sites_detected_default_filter'] = $row[0];

	print_r($stats);

    $conn->beginTransaction();

	foreach($stats as $name => $value) {
        $conn->query("delete from stats_cache where name = ?",
            array($name)
            );
		$conn->query(
			"insert into stats_cache (name, value) values (?,?)",
			array($name, $value)
			);
	}

	$conn->commit();

} elseif ($argv[1] == 'isps') {

	$rs = $conn->query("select network_name, url_latest_status.status, count(*) ct
	from url_latest_status
	inner join isps on (isps.name = network_name)
	inner join urls using (urlID)
	where show_results = 1 and source = 'alexa'
	group by network_name, url_latest_status.status
	order by network_name, url_latest_status.status", array());

	$row = $rs->fetch();
	
	while ($row) {
		$last = $row['network_name'];
		$out = array(
			'ok' => 0,
			'blocked' => 0,
			'error' => 0,
			'dnsfail' => 0,
			'timeout' => 0
			);

		
		# primitive grouping by ISP
		do {
			if (in_array($row['status'], array('ok','blocked','timeout','error','dnsfail'))) {
				$out[ $row['status'] ] = $row['ct'];
				@$out['total'] += $row['ct'];
			}
			$row = $rs->fetch();
		} while ($row && $row['network_name'] == $last);

		print_r($out);

        $q = $conn->query("update isp_stats_cache set ok = ?, blocked=?, timeout=?, error=?, dnsfail=?, total=?
        where network_name = ?",
        array(
            $out['ok'],
            $out['blocked'],
            $out['timeout'],
            $out['error'],
            $out['dnsfail'],
            $out['total'],
            $last
            )
        );
        if ($q->rowCount() == 0) {

            $q = $conn->query("insert into isp_stats_cache(network_name, ok, blocked, timeout, error, dnsfail, total)
            values (?,?,?,?,?,?,?)", 
            array($last, 
                $out['ok'],
                $out['blocked'],
                $out['timeout'],
                $out['error'],
                $out['dnsfail'],
                $out['total']
                )
            );
        }

	} while ($row);

} elseif ($argv[1] == 'block-category') {
    $conn->beginTransaction();
    $q = $conn->query("delete from stats.category_stats");
    $q = $conn->query("insert into stats.category_stats
        select category, network_name, count(*) 
        from url_latest_status 
        where category is not null and category <> '' and status='blocked' 
        group by category, network_name");
    $conn->commit();
} elseif ($argv[1] == 'domain-category') {
    $conn->beginTransaction();
    $rs = $conn->query("select * from tags where type = ?",
        array('domain'));
    foreach($rs as $row) {
        $conn->query("delete from stats.domain_stats where id = ?",
            array($row['id']));

        $q = $conn->query("select count(distinct urlid) as blockcount 
            from url_latest_status uls 
            inner join urls using(urlid) 
            where tags && makearray(?)",
            array($row['id'])
            );
        $blockcount = $q->fetchone();
        $q = $conn->query("select count(*) from urls where tags && makearray(?)",
            array($row['id'])
            );
        $totalcount = $q->fetchone();
        $conn->query("insert into stats.domain_stats (name, description, block_count, total)
            values (?,?,?,?)",
            array($row['name'], $row['description'], $blockcount, $totalcount)
            );

    }
    $conn->commit();

}

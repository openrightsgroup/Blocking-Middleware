<?php

include "../1.2/libs/DB.php";

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$rs = $conn->query("select usc.*,url from url_status_changes usc
inner join urls using (urlid)
where old_status is null and new_status = 'blocked' order by id desc limit 25;", 
array());

function ent($s) {
	return htmlspecialchars($s, ENT_NOQUOTES|ENT_IGNORE, "utf-8");
}

?>
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Blocked.org.uk results: new blocked</title>
        <link>http://www.blocked.org.uk</link>
        <description>RSS Feed of newly detected blocked results</description>
        <language>en-gb</language>
<?php while ($item = $rs->fetch_assoc()): ?>
        <item>
            <title><?php echo ent($item['url'])?> is blocked on <?php echo ent($item['network_name'])?></title>
            <description><?php echo ent($item['url'])?> is blocked on <?php echo ent($item['network_name'])?></description>
            <link>http://www.blocked.org.uk/results?url=<?php echo urlencode($item['url'])?></link>
            <pubDate><?php echo date("r",strtotime($item['created']))?></pubDate>
        </item>
<?php endwhile?>
    </channel>
</rss>


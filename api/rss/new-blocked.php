<?php

include "../1.2/libs/DB.php";

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$rs = $conn->query("select usc.*,url, category from url_status_changes usc
inner join urls using (urlid)
inner join url_latest_status uls on (uls.urlid = usc.urlid and uls.network_name = usc.network_name)
where old_status is null and new_status = 'blocked' order by id desc limit 25;", 
array());

function ent($s) {
	return htmlspecialchars($s, ENT_NOQUOTES|ENT_IGNORE, "utf-8");
}

header("Content-type: application/rss+xml");

?>
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <atom:link href="https://api.blocked.org.uk/rss/new-blocked" rel="self" type="application/rss+xml" />
        <title>Blocked.org.uk results: new blocked</title>
        <link>http://www.blocked.org.uk</link>
        <ttl>15</ttl>
        <lastBuildDate><?php echo date("r"); ?></lastBuildDate>
        <description>RSS Feed of newly detected blocked results</description>
        <language>en-gb</language>
<?php while ($item = $rs->fetch_assoc()): ?>
        <item>
            <title><?php echo ent($item['url'])?> is blocked on <?php echo ent($item['network_name'])?></title>
            <description><?php echo ent($item['url'])?> is blocked on <?php echo ent($item['network_name'])?>
<?php if ($item['category']): ?>
	    , in category <?php echo ent($item['category'])?>
<?php endif?>
            </description>
            <link>http://www.blocked.org.uk/results?url=<?php echo urlencode($item['url'])?></link>
            <pubDate><?php echo date("r",strtotime($item['created']))?></pubDate>
            <guid isPermaLink="false">new-blocked-<?php echo $item['id'];?></guid>
        </item>
<?php endwhile?>
    </channel>
</rss>


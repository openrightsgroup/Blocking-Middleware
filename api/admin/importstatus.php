<?php

require "../1.2/libs/DB.php";

$db = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$ttl = $db->query("select count(*) as ct from urls where source = ?",
    array($_GET['source']));
$ttlrow = $ttl->fetch_array();

$ttl2 = $db->query("select count(distinct urlid) ct from results 
inner join  urls using (urlid)
where source = ?",
    array($_GET['source']));
$ttl2row = $ttl2->fetch_array();


$res = $db->query("select results.status, 
    count(distinct urlid) url_count, count(distinct urlid, network_name) block_count
    from urls 
    inner join results using (urlid)
    where source = ?
    group by results.status
    order by results.status",
    array($_GET['source'])
    );

?>
<!DOCTYPE html>
<html>
<head>
<link href="http://netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css" rel="stylesheet" />
<title>API Admin :: Bulk import status</title>
<style type="text/css">
.num { text-align: right }
</style>
</head>
<body>
    <?php include "nav.php"?>
    <div class="container">
    <div class="row">

    <h3>Results - <?php echo $_GET['source']?></h3>
    <p>Total URLs: <?php echo $ttlrow['ct']?></p>
    <p>URLs Tested: <?php echo $ttl2row['ct']?></p>

    <table class="table">
    <tr>
    <th>Status</th>
    <th class="num">URL Count</th>
    <th class="num">Result Count</th>
    </tr>
    <?php while ($data = $res->fetch_array()): ?>
    <tr>
      <td><?php echo $data['status'] ?></td>
      <td class="num"><?php echo $data['url_count'] ?></td>
      <td class="num"><?php echo $data['block_count'] ?></td>
    </tr>
    <?php endwhile ?>
    </table>

    <p>Download <a href="results.php?mode=full&source=<?php echo $_GET['source']?>">full results</a> or
    <a href="results.php?mode=table&source=<?php echo $_GET['source']?>">tabulated summary</a>
    </p>

    </div>
    </div>
</body>
</html>

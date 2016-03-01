<?php

require "page.inc.php";
require "../1.2/libs/DB.php";

$db = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$recent = $db->query("select source, max(urlid), max(inserted)
    from urls 
    where source not in ('user','dmoz')
    group by source
    having max(inserted) > date_sub(now(), interval 30 day)
    order by max(inserted) desc",
    array()
    );

page_top("API Admin :: Bulk load");
?>

<h1>Bulk load</h1>

<div class="row">

<div class="col-sm-6">

<form class="form" action="import.php" method="POST" enctype="multipart/form-data">
<div class="form-group">
<label for="source">Source:</label>
<input class="form-control" type="text" name="source" />
<p class="help-block">The source tag is a short word or phrase used to identify this set of URLs in reports.</p>
</div>
<div class="form-group">
<label for="file1">File:</label>
<input class="form-control" type="file" name="file1" />
<p class="help-block">The list file should be a plain text file, with one URL per line.</p>
</div>
<input class="btn btn-primary" type="submit" value="Submit" />
</form>

</div>

<div class="col-sm-6">
<div class="well">
<h4>Recent bulk uploads</h4>
    <ul>
    <?php while ($data = $recent->fetch_array()): ?>
    <li><a href="importstatus.php?source=<?php echo $data['source']; ?>">
        <?php echo $data['source'] ?>
        </a>
    </li>
    <?php endwhile ?>
    </ul>
</div>
</div>

</div>
    
<?php page_bottom();?>
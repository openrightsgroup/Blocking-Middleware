<?php

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

?>
<!DOCTYPE html>
<html>
<head>
<link href="http://netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css" rel="stylesheet" />
<title>API Admin :: Home</title>
</head>
<body>
    <?php include "nav.php"?>
    <div class="container">
    <?php include "messages.php"?>
    <div class="row">
    <div class="col-xs-4 ">
    <h3>Data Management</h3>
    <ul>
    <li><a href="load.php">Bulk load URLs</a></li>
    </ul>

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
    <div class="col-xs-4 ">
    <h3>User Management</h3>
    <ul>
    <li>Manage users</li>
    <li>Manage probes</li>
    </ul>
    <p><em>Coming soon...</em></p>

    </div>
    <div class="col-xs-4">
    <h3>Reports</h3>
    <ul>
    <li><a href="http://api.blocked.org.uk:5000">DMOZ browser prototype</a></li>
    <li><a href="http://api.blocked.org.uk:5020">Blocked reports</a></li>
    </div>



    </div><!-- /.row -->
    <div class="row">
        <div class="col-xs-6">        
        <h3>URL management</h3>
        
        <form class="form-inline" action="recheck.php">
        <div class="form-group">
        <label class="sr-only" for="url">URL to force-check</label>
        <input type="text" class="form-control" name="url" placeholder="URL to force-check" /><input class="btn btn-default" type="submit" value="submit" />
        </div>
        </form>
        
        </div>
    </div>
        
    
    
    </div> <!-- /.container -->

<script src="http://netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
</body>
</html>

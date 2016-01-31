<!DOCTYPE html>
<html>
<head>
<link href="http://netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css" rel="stylesheet" />
<title>API Admin :: Bulk load</title>
</head>
<body>
<?php include "nav.php"?>

    <div class="container">
<h1>Bulk load</h1>

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

<script src="http://netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
</body>
</html>

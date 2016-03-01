<?php

function page_top($title) {
?>
<!DOCTYPE html>
<html>
<head>
<link href="http://netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css" rel="stylesheet" />
<title><?php echo $title?></title>
<style type="text/css">
.num { text-align: right }
</style>
</head>
<body>
    <?php include "nav.inc.php"?>
    <div class="container">
    <?php include "messages.inc.php";?>


<?php
}

function page_bottom() {
?>

    </div> <!-- /.container -->
<script src=https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js></script>
<script src="http://netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
</body>
</html>


<?php
}
?>
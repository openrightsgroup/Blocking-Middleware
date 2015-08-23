<?php

include "../1.2/libs/amqp.php";
$ch = amqp_connect_full();

$networks = array(
'vodafone' => 'Vodafone',
'o2' => 'O2',
'ee' => 'EE'
);

$url = $_POST['url'];
$id = md5($url);
$msgbody = json_encode(array('url' => $url, 'hash' => $id));

$ex = new AMQPExchange($ch);
$ex->setName('org.blocked');
$ex->publish($msgbody, "admin.view.$id", AMQP_NOPARAM, array('priority'=>2));
?>

<!DOCTYPE html>
<html>
<head>
<link href="http://netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css" rel="stylesheet" />
<title>API Admin :: Manual verification</title>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7/jquery.js"></script>
</head>
<body>

<h1>Manual Verification</h1>

<form action="view.php" method="POST">
<label for="url">URL:</label>
<input type="text" name="url" />
<input type="submit" value="Submit" />
</form>

<ul class="nav nav-tabs">
<?foreach ($networks as $network => $name): ?>
<li><a href="#<?=$network?>" data-toggle="tab"><?=$name?></a> </li>
<?endforeach?>
</ul>

<div class="tab-content">
<?foreach ($networks as $network => $name): ?>
  <div class="tab-pane" id="<?=$network?>">
    <iframe src="content.php?network=<?=$network?>"></iframe>
  </div>
<?endforeach?>
</div>

<script type="text/javascript">
    jQuery(document).ready(function ($) {
        $('#tabs').tab();
    });
</script>
<script src="http://netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
</body>
</html>


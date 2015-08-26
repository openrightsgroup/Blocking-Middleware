<?php

include "../1.2/libs/amqp.php";
$ch = amqp_connect();

$networks = array(
'vodafone' => 'Vodafone',
'o2' => 'O2',
'ee' => 'EE',
'aaisp' => 'AAISP',
'bt' => 'BT',
'talktalk' => 'TalkTalk',
'plusnet' => 'PlusNet',
'sky' => 'Sky'

);

$url = $_POST['url'];
$id = md5($url);
$msgbody = json_encode(array('url' => $url, 'hash' => $id));

?>

<!DOCTYPE html>
<html>
<head>
<link href="http://netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css" rel="stylesheet" />
<title>API Admin :: Manual verification</title>
<style type="text/css">
    .frame {
        -ms-zoom: 0.6;
        -moz-transform: scale(0.6);
        -moz-transform-origin: 0 0;
        -o-transform: scale(0.6);
        -o-transform-origin: 0 0;
        -webkit-transform: scale(0.6);
        -webkit-transform-origin: 0 0; 
        width: 600px;
        height: 340px;
    }
</style>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7/jquery.js"></script>
</head>
<body>

<div class="container">
<h1>Manual Verification</h1>

<form action="view.php" method="POST">
<label for="url">URL:</label>
<input type="text" name="url" value="<?php echo $url?>" />
<input type="submit" value="Submit" />
</form>

<ul class="">
<?php foreach ($networks as $network => $name): ?>
<li><a href="#<?php echo $network?>" ><?php echo $name?></a> </li>
<?php endforeach?>
</ul>

<div class="row">
<?php foreach ($networks as $network => $name): ?>
<?php
$q = new AMQPQueue($ch);
$q->setName("view.$network.$hash");
$q->setArgument("x-expires", 25000);
$q->declare();
$q->bind("org.blocked", "admin.results.$network.$hash");
?>
  <div class="col-lg-4 col-md-6 col-sm-12 col-xs-12" id="<?php echo $network?>" style="border: 1px solid gray; float: left">
    <h2><a name="<?php echo $network?>"><?php echo $name?></a></h2>
    <iframe  class="frame" src="content.php?network=<?php echo $network?>&hash=<?php echo $id?>"></iframe>
  </div>
<?php endforeach?>
</div>

</div>

<script type="text/javascript">
    jQuery(document).ready(function ($) {
        //$('#tabs').tab();
    });
</script>
<script src="http://netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
</body>
</html>
<?php

ob_flush();

$ex = new AMQPExchange($ch);
$ex->setName('org.blocked');
if ($url) {
    $ex->publish($msgbody, "admin.view.$id", AMQP_NOPARAM, array('priority'=>2));
}

?>

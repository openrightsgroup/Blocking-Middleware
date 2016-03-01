<?php
session_start();
if (@$_SESSION['messages']) {
    while ($msg = array_pop($_SESSION['messages'])) {
?>
<div class="alert alert-info"><?php echo $msg?></div>
<?php
    }
}
?>
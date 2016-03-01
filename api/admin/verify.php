<?php

require "page.inc.php";

page_top("API Admin :: Manual verification");
?>
<h1>Manual Verification</h1>

<form action="view.php" method="POST">
<label for="url">URL:</label>
<input type="text" name="url" />
<input type="submit" value="Submit" />
</form>

<?php
page_bottom();
?>
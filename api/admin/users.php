<?php

require "page.inc.php";
require "../1.2/libs/DB.php";

$db = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$res = $db->query("select email, fullname, status, createdat from users order by id desc");

page_top("API Admin :: Users");
?>
<h1>User Management</h1>

<table class="table">
  <tr>
    <th>Email</th>
    <th>Full Name</th>
    <th>Status</th>
    <th>Created</th>
    <th>Actions</th>
  </tr>
<?php while ($user = $res->fetch_array()): ?>  
  <tr>
    <td><?php echo $user['email']?></td>
    <td><?php echo $user['fullName']?></td>
    <td><?php echo $user['status']?></td>
    <td><?php echo $user['createdAt']?></td>
    <td>
    <?php if ($user['status'] == "ok"):?>
      <a class="btn-xs btn-warning" href="#">Suspend</a> <a class="btn-xs btn-danger" href="#">Ban</a>
    <?php elseif ($user['status'] == "suspended"): ?>
      <a class="btn-xs btn-success" href="#">Restore</a>
    <?php elseif ($user['status'] == "pending"):?>
      <a class="btn-xs btn-success" href="#">Approve</a>
    <? endif ?>    
    </td>
  </tr>
<?php endwhile ?>
</table>
<?php
page_bottom();
?>
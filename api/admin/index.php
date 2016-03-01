<?php

require "page.inc.php";
require "../1.2/libs/DB.php";

$db = new APIDB($dbhost, $dbuser, $dbpass, $dbname);


$recent_urls = $db->query("select URL, inserted, lastPolled
from urls
order by urlid desc
limit 10",array());

page_top("API Admin :: Home");
?>
    <div class="row">
        
        
        <h3>URL management</h3>
        <div>
        <form class="form-inline" action="recheck.php">
        <div class="form-group">
        <label class="sr-only" for="url">URL to force-check</label>
        <input type="text" class="form-control" name="url" placeholder="URL to force-check" /><input class="btn btn-default" type="submit" value="submit" />
        </div>
        </form>
        </div>

        <h4>Recently submitted URLs</h4>
        
        <table class="table">
        <tr>
          <th>URL</th>
          <th>Submission date</th>
          <th>Last tested</th>
        </tr>
        <?php foreach ($recent_urls as $url):?>
        <tr>
          <td><?php echo $url['URL']; ?></td>
          <td><?php echo $url['inserted']; ?></td>
          <td><?php echo $url['lastPolled']; ?></td>
        </tr>
        <?php endforeach ?>
        </table>
        
    </div>
        
    
    
<?php page_bottom();?>
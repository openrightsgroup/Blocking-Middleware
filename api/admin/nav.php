  <nav class="navbar navbar-inverse">
    <div class="container">
      <div class="navbar-header">
        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
          <span class="sr-only">Toggle navigation</span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
        <a class="navbar-brand" href="/admin/">Blocked.org.uk Admin</a>
      </div>
      <div id="navbar" class="collapse navbar-collapse">
        <ul class="nav navbar-nav">
          <li class="<?php echo ($_SERVER['PHP_SELF'] == "/admin/index.php")? "active":"";?> "><a href="/admin/">Home</a></li>
          <li class="<?php echo ($_SERVER['PHP_SELF'] == "/admin/load.php")? "active":"";?> "><a href="load.php">Bulk URL Load</a></li>
          <!--<li class=""><a href="verify.php">Manual Verification</a></li>-->
        </ul>
      </div><!--/.nav-collapse -->
    </div>
  </nav>
  
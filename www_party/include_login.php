<?php

include "sceneid.php";

if (!defined("ADMIN_DIR")) exit();

if (is_user_logged_in())
{
  redirect( build_url("News",array("login"=>"alreadyloggedin")) );
}

run_hook("login_start");

if ($_POST["login"])
{
  $_SESSION["logindata"] = NULL;

  $userID = SQLLib::selectRow(sprintf_esc("select id from users where `username`='%s' and `password`='%s' and `remote`='0'",$_POST["login"],hashPassword($_POST["password"])))->id;

  run_hook("login_authenticate",array("userID"=>&$userID));

  if ($userID)
  {
    $_SESSION["logindata"] = SQLLib::selectRow(sprintf_esc("select * from users where id=%d",$userID));
    header( "Location: ".build_url("News",array("login"=>"success")) );
  }
  else
  {
    header( "Location: ".build_url("Login",array("login"=>"failure")) );
  }
  exit();
}

if ($_GET["login"]=="failure")
  echo "<div class='error'>Login failed!</div>";
?>

<p> Login Local </p>
<form action="<?=build_url("Login")?>" method="post" id='loginForm'>
<div>
  <label for="loginusername">Username:</label>
  <input id="loginusername" name="login" type="text" required='yes' />
</div>
<div>
  <label for="loginpassword">Password:</label>
  <input id="loginpassword" name="password" type="password" required='yes' />
</div>
<div>
<br />
  <input type="submit" value="Go!" />
</div>
</form>
<p> Login Remote </p>

<div style="image-rendering: auto; text-align: left">
	<a href="/index.php?page=Login&sceneid=1"><img alt="scene id" src="include/SceneID_Icon_300x48.png"></a>
</div>

<?php
run_hook("login_end");
?>

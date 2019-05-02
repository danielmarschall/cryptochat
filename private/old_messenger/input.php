<?php

include 'col.inc.php';

if ($_POST['txt']!="") {
	$fileOpen=@fopen("messages.txt", "a");
	$_POST['txt'] = stripslashes($_POST['txt']);
	$_POST['txt'] = htmlentities($_POST['txt']);
	$_POST['txt'] = str_replace('"', '&quot;', $_POST['txt']);
	@fwrite($fileOpen,date('d.m.Y H:i:s')." <font color=\"".str_to_color($_COOKIE['mov_nick'])."\"><b>".$_COOKIE['mov_nick']." -- </b></font>".$_POST['txt']."<br>\n");
	@fclose($fileOpen);
}

echo "<style type=\"text/css\">BODY{font-family:Verdana, Arial, Helvetica, sans-serif}</style>
<form method=\"post\" action=\"input.php\" name=\"inpform\">
          <input name=\"txt\" type=\"text\" size=\"75\" id=\"txt\">
          <input type=\"submit\" name=\"Submit\" value=\"   Chat!   \">
</form>        <a href=\"logoff.php\" target=\"_top\">Log Off</a>

-- <a href=\"messages.php?show=all\" target=\"_blank\">Show full history</a>

-- <a href=\"files/\" target=\"_blank\">Show shared files</a><br>


<script type=\"text/javascript\" language=\"JavaScript\">
	<!--
		self.focus();
		document.inpform.txt.focus();
	// -->
	</script>

";
?>
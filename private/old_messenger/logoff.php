<?php

setcookie ("mov_nick", "", time() - 3600);

include 'col.inc.php';

$fileOpen=@fopen("messages.txt", "a");
@fwrite($fileOpen,date("d.m.Y H:i:s")." <font color=\"".str_to_color($_COOKIE['mov_nick'])."\"><b>".$_COOKIE['mov_nick']."</b> <i>hat dem Raum verlasssen</i></font><br>\n");
fclose($fileOpen);
//echo "<style type=\"text/css\">BODY{font-family:Verdana, Arial, Helvetica, sans-serif}</style><a href=\"index.php\" target=\"_top\">Login Again!</a>";

unset($_COOKIE['mov_nick']); 

header("Location:index.php");

?>
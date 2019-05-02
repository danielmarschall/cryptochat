<?php

include 'col.inc.php';

if(!isset($_POST['nickname'])){
echo"<style type=\"text/css\">BODY{font-family:Verdana, Arial, Helvetica, sans-serif}</style>

<h1>MOV Login</h1>
<form method=\"post\" action=\"index.php\">
 <p>Please enter your name code:</p>
  <input name=\"nickname\" type=\"text\" id=\"nickname\">
  <input type=\"submit\" value=\"   Login   \">
</form>

<p><a href=\"messages.php?show=all\" target=\"_blank\">Show only message log</a></p>

</body>
</html>";
}
else{
setcookie("mov_nick",$_POST['nickname'],0/*,0,"/",""*/);
$fileOpen=@fopen("messages.txt", "a");
@fwrite($fileOpen,date("d.m.Y H:i:s")." <font color=\"".str_to_color($_POST['nickname'])."\"><b>".$_POST['nickname']."</b> <i>betritt den Raum</i></font><br>\n");
fclose($fileOpen);
header("Location:chat.php");
}
?>
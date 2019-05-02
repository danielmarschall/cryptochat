<?php

if (!isset($_COOKIE['mov_nick'])) {
	header('location:index.php');
	die();
}

if (isset($_GET['show'])) {
	$show = $_GET['show'];
} else {
	$show = 20;
}

?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">
<html>
<head>
<title>MOV Alpha</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>

<frameset rows="*,90" cols="*" framespacing="1" frameborder="yes" border="1" bordercolor="#FF0000">
    <frame src="messages.php?show=<?php echo $show; ?>" name="mainFrame">

  <frame src="input.php" name="bottomFrame" scrolling="NO" noresize >
</frameset>
<noframes><body>

</body></noframes>
</html>

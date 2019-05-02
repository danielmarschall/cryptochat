<?php

if ($_GET['show'] != 'all') {
	echo '<meta http-equiv="refresh" content="2">';
}

echo '<style type="text/css">BODY{font-family:Verdana, Arial, Helvetica, sans-serif}</style>';

$fileRead = file_get_contents("messages.txt");

$expl = explode("\n", $fileRead);

$e = count($expl);

// -1, da am Dateiende immer "\n" (Leerzeile)

if ($_GET['show'] == 'all') {
	$s = count($expl);
} else {
	$s = $_GET['show'];
}

$s = count($expl)-$s-1;
if ($s < 0) $s = 0;

for ($i=$s; $i<count($expl)-1; $i++) {
	echo "[".str_pad($i+1, 4, '0', STR_PAD_LEFT)."] ".$expl[$i];
}

?>

<!-- <script>
scrollto(0,2048);
</script> -->

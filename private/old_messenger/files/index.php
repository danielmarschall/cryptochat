<?php

require 'DirectoryHashCalculator.class.php';

define('VER', '09.03.2010 21:30');

error_reporting(E_ALL | E_NOTICE);

$path = dirname($_SERVER['SCRIPT_NAME']);

?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
		"http://www.w3.org/TR/html4/loose.dtd">

<html>

<head>
	<title>Index f&uuml;r <?php echo $path; ?></title>
</head>

<body>

<h1>Index f&uuml;r <?php echo $path; ?></h1>

<hr>

<br><table border="1" width="100%">
<tr>
	<td><b>Dateiname</b></td>
	<td><b>Hochgeladen / Aktualisiert</b> (Sort)</td>
	<td align="right"><b>Dateigr&ouml;&szlig;e</b></td>
	<td align="right"><b>MD5-Hash</b></td>
</tr><?php

$outarray = array();
$sizesum = 0;
$filesum = 0;
$hash_calculator = new DirectoryHashCalculator();
dir_rekursiv('', $outarray, $sizesum, $filesum, $hash_calculator);
ksort($outarray);

foreach ($outarray as $value) {
	echo $value;
}

?>
</table><br>

<?php


$filesum = number_format($filesum, 0, ',', '.');
$sizesum = number_format($sizesum, 0, ',', '.');

/*

Note:
	In this solution, ".*" and "index.html" (this script) and
	"DirectoryHashCalculator.class.php" (required by this script)
	are not calculated in because dir_rekursiv() ignored them,
	so the Directory-Hash-Result may differ from other applications which
	implement it.
	
*/

$directory_hash = $hash_calculator->calculateDirectoryHash();
$hash_alg = $hash_calculator->getVersionDescription();

echo '<p><code>Berechnung abgeschlossen am '.date('Y-m-d H:i:s \G\M\TO').'<br>';
echo "$filesum Dateien mit $sizesum Byte Gr&ouml;&szlig;e.<br>";
echo "Directory-Hash ($hash_alg): $directory_hash.</code></p>";
echo '<hr>';
echo '<p><i>Directory Listing Script Version '.VER.' &copy; 2010 <a href="http://www.viathinksoft.de/">ViaThinkSoft</a>.</i></p>';

?>

</body>

</html><?php

// Ref: http://www.php.net/manual/de/function.urlencode.php#96256
function encode_full_url($url) {
	$url = urlencode($url);
	$url = str_replace("%2F", "/", $url);
	$url = str_replace("%3A", ":", $url);
	$url = str_replace("+", "%20", $url); // Neu
	return $url;
}

function dir_rekursiv($verzeichnis, &$outarray, &$sizesum, &$filesum, $hash_calculator) {
	if ($verzeichnis == '') $verzeichnis = './';
	
	$handle = opendir($verzeichnis);
	while ($datei = readdir($handle)) 
	{
		if (($datei != '.') && ($datei != '..')) 
		{
			$file = $verzeichnis.$datei;
			if (is_dir($file)) // Wenn Verzeichniseintrag ein Verzeichnis ist
			{
				// Erneuter Funktionsaufruf, um das aktuelle Verzeichnis auszulesen
				dir_rekursiv($file.'/', $outarray, $sizesum, $filesum, $hash_calculator); 
			} else {
				// Wenn Verzeichnis-Eintrag eine Datei ist, diese ausgeben
				
				if (substr($file, 0, 2) == './') {
					$file = substr($file, 2, strlen($file)-2); // './' entfernen
				}
				
				if (($file != 'index.php') &&
					($file != 'DirectoryHashCalculator.class.php') &&
					(substr($file, 0, 1) != '.')) {
						$filesize = filesize($file);
						$sizesum += $filesize;
						$filesum++; 
						$sizeformat = number_format($filesize, 0, ',', '.');
						$file_md5 = $hash_calculator->addFile($file);
						if ($file_md5 === false) $file_md5 = '<b>ERROR!</b>';
						$mtime = filemtime($file);
						$date = date('Y-m-d H:i:s \G\M\TO', $mtime); 
						if (!isset($outarray[$mtime])) $outarray[$mtime] = '';
					$outarray[$mtime] .= '<tr>
	<td><a href="'.encode_full_url($file).'" target="_blank">'.str_replace('/', ' / ', $file).'</a></td>
	<td>'.$date.'</td>
	<td align="right">'.$sizeformat.'</td>
	<td align="right"><code>'.$file_md5.'</code></td>
</tr>';
					}
			}
		}
	}
	closedir($handle);
}

?>
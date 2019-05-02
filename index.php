<?php

// ViaThinkSoft CryptoChat
// (C) 2014-2018 by Daniel Marschall, ViaThinkSoft
// Licensed under the terms of GPLv3

// -----------------------------------------------------------------------------------------------

// feature: user comes online, user comes offline, user is inactive, user is active, user is typing, get user list
// todo: hide password length to avoid screensaves revealing confidential information
// feature: split screen (frames), with united document.title ! (sum unread posts of each chat)
// ng: smileys
// ng: roomname, username encrypt
// ng: sha3
// ng: maybe it would be usefil if you could see by different colors if something was encrypted using a different password
// bug: some browsers cannot mark the last line, otherwise everything will be marked
// bug: offline detection does not work...
// todo: "reset chat" (counter=0, document='') while pwd change?
// feature: verhindern dass man etwas postet ohne ein passwort eingegeben zu haben? -> config
// feature: posts durchnumerieren?
// bug openpandora: password field on change geht net (cannot repro all the time?) ist !== null ok? (fixed now?)
// bug cheery laptop: froze, didn't check new messages (mehrfach. repro? Aurora 24.2.0 Gentoo). eigene kommen nicht an. nach einer weile geht es plötzlich wieder? evtl ist vts request 1 mal fehlgeschlagen. (fixed now with fail callbacks?)
// feature: shift+enter = new line without sending?
// rp feature: ((->[i] , rp notizen + cors
// feature: send files?
// feature: dereferer
// feature: configuration option to show a drop down box with all chat rooms
// todo: always renew cache
// todo: warn if config is not correct, e.g. admin password missing
// feature: prevent making screenshots? keep clipboard empty?
// feature: detect NoScript
// todo: onChatHide() does not get called when tab is active (and visible), but browser window hasn't the focus
// feature: loginmessage. show version of user. user soll bei jedem ajax-verkehr die version mitsenden, damit der server ihn ablehnen kann wenn die version zu alt ist!. ggf ein re-load forcing (self.reload in same room/user/pwd)?
// idea: undo decryption if pwd field changed? delete pwd field and save actual decryption key in VAR array. so no screenloggers can see
// feature?: possibility to disable hash? just try to do AES.
// feature?: possibility to disable user stats or to prune them.
// feature?: possibility to prune chat
// feature?: send pwd hashed with refresh() so you can see which key(s) the active users use?
// feature?: at "(Logging off)"-Message sending: remove DIRECTLY from stat list
// feature: when configuration symbols are not defined, redefine them with default settings
// feature: online list - instead of showing 2 same names, just write "x2"

// -----------------------------------------------------------------------------------------------

if (!file_exists(__DIR__ . '/config/config.inc.php')) {
	die('ERROR: File <b>config/config.inc.php</b> does not exist. Please create it using <b>config/config.original.inc.php</b>');
}
require __DIR__ . '/config/config.inc.php';
define('PRODUCT_NAME', 'CryptoChat');
define('MCC_VER',      trim(file_get_contents(__DIR__ . '/VERSION')));

require __DIR__ . '/' . DEP_DIR_SAJAX       . '/php/sajax.php';
require __DIR__ . '/' . DEP_DIR_JSONWRAPPER . '/jsonwrapper.php'; // TODO: make optional. only required if running with PHP 5.1

header('Content-type: text/html; charset=utf-8');

if ((REQUIRE_TLS) && (!is_secure())) {
	// TODO: redirect
	echo '<h1>Please connect via HTTPS or change <b>REQUIRE_TLS</b> to false.</h1>';
	die();
}

define('ACTION_LIST_ROOMS',  1);
define('ACTION_CREATE_ROOM', 2);

$ses_room  = (isset($_POST['ses_room']))  ? trim($_POST['ses_room'])  : '';
$ses_user  = (isset($_POST['ses_user']))  ? trim($_POST['ses_user'])  : '';
$admin_pwd = (isset($_POST['admin_pwd'])) ? trim($_POST['admin_pwd']) : '';

$ses_room = trim(strtolower($ses_room));
$ses_user = trim($ses_user);
// $admin_pwd will be trimmed in adminAuth()

$chat_exists = (!CHAT_MUST_EXIST) || (adminAuth($admin_pwd, ACTION_CREATE_ROOM)) || chat_exists($ses_room);

$is_logged_in = ($ses_room != '') && ($ses_user != '') && $chat_exists;
$show_list    = isset($_REQUEST['list_chatrooms']) && (!LIST_CHATS_REQUIRE_PWD || adminAuth($_POST['admin_pwd'], ACTION_LIST_ROOMS));

if ($is_logged_in) {
	$chatroom_file = chatroom_file($ses_room);
	touch($chatroom_file);
}

function adminAuth($password, $action) {
	if ($action == ACTION_LIST_ROOMS) {
		// if (trim(PWD_LIST_ROOMS) == '') return false;
		return (trim($password) == trim(PWD_LIST_ROOMS));
	} else if ($action == ACTION_CREATE_ROOM) {
		// if (trim(PWD_CREATE_ROOM) == '') return false;
		return (trim($password) == trim(PWD_CREATE_ROOM));
	} else {
		throw new Exception('Unknown action');
	}
}

function is_secure() {
	// https://stackoverflow.com/questions/1175096/how-to-find-out-if-you-are-using-https-without-serverhttps
	if(isset($_SERVER['HTTPS'])) {
		if ($_SERVER['HTTPS'] == 'on') {
			return true;
		}
	}
	return false;
}

function chat_exists($room) {
	return file_exists(chatroom_file($room));
}

function chatroom_file($room) {
	return __DIR__ . '/chats/'.escape_filename($room).'.html';
}

function chatroom_userstat_file($room, $unique_id) {
	return __DIR__ . '/chats/'.escape_filename($room).'_'.escape_filename($unique_id).'.session';
}

function chatroom_userstat_files($room) {
	$ses_ids = array();

	$ary = glob(__DIR__ . '/chats/'.escape_filename($room).'_*.session');
	foreach ($ary as &$a) {
		if (!preg_match('@'.preg_quote(__DIR__ . '/chats/'.escape_filename($room).'_').'(.+)\.session@ismU', $a, $m)) {
			die('Internal error at '.__LINE__);
		}
		$ses_ids[] = $m[1];
	}

	return $ses_ids;
}

function write_user_stat($room, $unique_id, $username, $ip) {
	$file = chatroom_userstat_file($room, $unique_id);

	$cont  = '';
#	$cont .= "ROOM:$room\n";
	$cont .= "USERNAME:$username\n";
#	$cont .= "UNIQUEID:$unique_id\n";
	$cont .= "IP:$ip\n";
#	$now=time(); $cont .= "ACTIVE:$now\n";
#	$key=...; $cont .= "KEY:$key\n";

	file_put_contents($file, $cont);
}

function file_age($file) {
	return time()-filemtime($file);
}

function get_active_users($room, $max_inactivity=USERSTAT_INACTIVE_SECS) {
	$ses_ids = array();

	$all_ses_ids = chatroom_userstat_files($room);
	foreach ($all_ses_ids as &$ses_id) {
		$file = chatroom_userstat_file($room, $ses_id);
		if (file_age($file) <= $max_inactivity) {
			$ses_ids[] = $ses_id;
		} else {
			if (DELETE_OLD_USERSTATS) {
				$file = chatroom_userstat_file($room, $ses_id);
				unlink($file);
			}
		}
	}

	return $ses_ids;
}

function read_user_stat($room, $unique_id) {
	$res = array();

	$file = chatroom_userstat_file($room, $unique_id);

	if (!file_exists($file)) return false;

	$cont = file($file);
	foreach ($cont as &$c) {
		$c = trim($c);
		if ($c == '') continue;
		$nameval = explode(':', $c, 2);
		if (count($nameval) < 2) continue;

		$name = $nameval[0];
		$val  = $nameval[1];
		$res[$name] = $val;
	}
	$res['ACTIVE']   = filemtime($file);
	$res['ROOM']     = $room;
	$res['UNIQUEID'] = $unique_id;

	return $res;
}

function list_rooms() {
	$ary = glob(__DIR__ . '/chats/*.html');
	foreach ($ary as &$a) {
		$a = basename($a, '.html');
	}
	return $ary;
}

function count_messages($room) {
	$room_file = chatroom_file($room);
	return count(file($room_file));
}

function count_users($room) {
	$room_file = chatroom_file($room);
	$cont = file_get_contents($room_file);
	preg_match_all('@\(user:(.*)\)@ismU', $cont, $m);
	foreach ($m[1] as &$us) {
		$users_lookup[$us] = true;
	}
	return count($users_lookup);
}

function escape_filename($str) {
	$str = str_replace('/', '_', $str);
	return $str;
}

/* exported via AJAX */
function add_line($id, $room, $user, $msg) {
	if (!chat_exists($room)) return;
	if ($user == '') return;

	$f = fopen(chatroom_file($room), 'a');
	$dt = date('Y-m-d H:i:s');
//	$msg = stripslashes($msg);
	$remote = $_SERVER['REMOTE_ADDR'];

	// Escape stuff
	$user = htmlentities($user, ENT_QUOTES, 'UTF-8');
	$msg  = htmlentities($msg,  ENT_QUOTES, 'UTF-8');

	$msgs = explode("\n", $msg);
	foreach ($msgs as &$msg) {
		fwrite($f, "(date: $dt)(user: $user)$msg<br>\n");
	}

	fclose($f);

	return array($id);
}

/* exported via AJAX */
function refresh($room, $fromline, $username, $unique_id) {
	if (!chat_exists($room)) return;
	if ($fromline < 0) return;

	$unique_id = trim($unique_id);
	if ($unique_id == '') {
		# TODO: show error message
		return false;
	}
	write_user_stat($room, $unique_id, $username, $_SERVER['REMOTE_ADDR']);

	$res = '';
	$lines = file(chatroom_file($room));
	for ($i=$fromline; $i<count($lines); $i++) {
		$res .= $lines[$i] . "\n";
	}

	$userstats = array();
	$ses_ids = get_active_users($room);
	foreach ($ses_ids as &$ses_id) {
		$userstats[] = read_user_stat($room, $ses_id);
	}

	return array(count($lines), $res, $userstats);
}

// $sajax_debug_mode = true;
$sajax_failure_redirect = 'http://web.archive.org/web/20090915191608/http://sajax.info/sajaxfail.html';
sajax_export(
	array('name' => 'add_line', 'method' => 'POST'),
	array('name' => 'refresh',  'method' => 'GET')   // TODO: post?
);
sajax_handle_client_request();

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
	"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<META HTTP-EQUIV="content-type" CONTENT="text/html; charset=utf-8">
	<title><?php echo htmlentities(PRODUCT_NAME); ?></title>
	<style>
	body {
		background-color:#000011;
		color:white;
		font-family:Arial;
		font-size:13px;
	}
	body.chatroom {
		margin: 0;
		background-color:#000011;
		color:white;
		font-family:Arial;
		font-size:13px;
	}
	thead td {
		font-weight:bold;
		background-color:#000044;
	}
	.encrypted {
		color:orange;
	}
	.error {
		color:#dd0000;
	}
	input, textarea {
		background-color:#000011;
		color:white;
	}
	#postarea textarea {
		width:80%;
		background-color:#000011;
		color:white;
	}
	#header {
		padding:5px;
		position:fixed;
		background-color:#000044;
		color:white;
		width:100%;
		height: 50px;
	}
	#status {
		position:fixed;
		right:5px;
		top:5px;
	}
	#chatmessages {
		padding-bottom:115px;
		padding-top: 65px;
		padding-left: 5px;
		padding-right: 5px;
	}
	#activeusers {
		position:fixed;
		top:65px;
		/*bottom:115px;*/
		right:5px;
		/*height:100px;*/
		width:150px;
		background-color:#222222;
		padding:10px;
	}
	#postarea {
		padding:5px;
		position: fixed;
		bottom:0;
		height: 100px;
		width: 100%;
		background-color:#000044;
		color:white;
	}
	.button {
		/* http://www.drweb.de/magazin/browser-cursor-hand/ */
		cursor: hand; cursor: pointer;
		font-family: Arial;
		color:white;
		background-color: #000022;
		border-color:#000000;
		border-style: inset;
	}
	.button:hover {
		color:yellow;
	}
	label {
		/* http://www.drweb.de/magazin/browser-cursor-hand/ */
		cursor: hand; cursor: pointer;
	}
	label:hover {
		color:yellow;
	}
	a:link {
		color:#ffdd00;
	}
	a:visited {
		color:darkred;
	}
	a:active, a:hover {
		color:white;
	}
	.logout a {
		color:white;
	}
	.logout a:hover {
		color:orange;
	}
	.logout {
		margin-left: 20px;
	}
	</style>

<?php
if ($is_logged_in) {
?>

	<script type="text/javascript" src="<?php echo DEP_DIR_CRYPTOJS; ?>/rollups/sha256.js"></script>
	<script type="text/javascript" src="<?php echo DEP_DIR_CRYPTOJS; ?>/rollups/aes.js"></script>
	<script type="text/javascript" src="<?php echo DEP_DIR_CRYPTOJS; ?>/rollups/md5.js"></script>

	<script type="text/javascript" src="<?php echo DEP_DIR_SAJAX; ?>/php/json_stringify.js"></script>
	<script type="text/javascript" src="<?php echo DEP_DIR_SAJAX; ?>/php/json_parse.js"></script>
	<script type="text/javascript" src="<?php echo DEP_DIR_SAJAX; ?>/php/sajax.js"></script>

	<script type="text/javascript" src="<?php echo DEP_DIR_CRC32; ?>/crc32.js"></script>

	<script type="text/javascript">

	<?php
	sajax_show_javascript();
	?>

	var product = <?php echo json_encode(PRODUCT_NAME); ?>;
	var version = <?php echo json_encode(MCC_VER); ?>;
	var room = <?php echo json_encode($ses_room); ?>;
	var user = <?php echo json_encode($ses_user); ?>;
	var curseq = 0;
	var roomSalt = "<?php echo sha1(X_SALT.$ses_room); ?>";
	var curVisible = true;
	var backgroundSeqCount = 0;
	var blink = 0;
	var uniqueID = "";

	// --- Useful functions

	function scrollToBottom() {
		// http://www.sourcetricks.com/2010/07/javascript-scroll-to-bottom-of-page.html
		window.scrollTo(0, document.body.scrollHeight);
	}

	function randNum(min, max) {
		return Math.floor((Math.random()*max)+min);
	}

	// http://stackoverflow.com/questions/1349404/generate-a-string-of-5-random-characters-in-javascript/1349426#1349426
	function randomString(len) {
		var text = "";
		var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

		for( var i=0; i < len; i++ ) {
			text += possible.charAt(Math.floor(Math.random() * possible.length));
		}

		return text;
	}

	// Array Remove - By John Resig (MIT Licensed)
	Array.prototype.remove = function(from, to) {
		var rest = this.slice((to || from) + 1 || this.length);
		this.length = from < 0 ? this.length + from : from;
		return this.push.apply(this, rest);
	};

	function sleep(milliseconds) {
		// http://www.phpied.com/sleep-in-javascript/
		var start = new Date().getTime();
		for (var i = 0; i < 1e7; i++) {
			if ((new Date().getTime() - start) > milliseconds) {
				break;
			}
		}
	}

	function get_html_translation_table(table, quote_style) {
		// From: http://phpjs.org/functions
		// +   original by: Philip Peterson
		// +    revised by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
		// +   bugfixed by: noname
		// +   bugfixed by: Alex
		// +   bugfixed by: Marco
		// +   bugfixed by: madipta
		// +   improved by: KELAN
		// +   improved by: Brett Zamir (http://brett-zamir.me)
		// +   bugfixed by: Brett Zamir (http://brett-zamir.me)
		// +      input by: Frank Forte
		// +   bugfixed by: T.Wild
		// +      input by: Ratheous
		// %          note: It has been decided that we're not going to add global
		// %          note: dependencies to php.js, meaning the constants are not
		// %          note: real constants, but strings instead. Integers are also supported if someone
		// %          note: chooses to create the constants themselves.
		// *     example 1: get_html_translation_table('HTML_SPECIALCHARS');
		// *     returns 1: {'"': '&quot;', '&': '&amp;', '<': '&lt;', '>': '&gt;'}
		var entities = {},
			hash_map = {},
			decimal;
		var constMappingTable = {},
			constMappingQuoteStyle = {};
		var useTable = {},
			useQuoteStyle = {};

		// Translate arguments
		constMappingTable[0] = 'HTML_SPECIALCHARS';
		constMappingTable[1] = 'HTML_ENTITIES';
		constMappingQuoteStyle[0] = 'ENT_NOQUOTES';
		constMappingQuoteStyle[2] = 'ENT_COMPAT';
		constMappingQuoteStyle[3] = 'ENT_QUOTES';

		useTable = !isNaN(table) ? constMappingTable[table] : table ? table.toUpperCase() : 'HTML_SPECIALCHARS';
		useQuoteStyle = !isNaN(quote_style) ? constMappingQuoteStyle[quote_style] : quote_style ? quote_style.toUpperCase() : 'ENT_COMPAT';

		if (useTable !== 'HTML_SPECIALCHARS' && useTable !== 'HTML_ENTITIES') {
			throw new Error("Table: " + useTable + ' not supported');
			// return false;
		}

		entities['38'] = '&amp;';
		if (useTable === 'HTML_ENTITIES') {
			entities['160'] = '&nbsp;';
			entities['161'] = '&iexcl;';
			entities['162'] = '&cent;';
			entities['163'] = '&pound;';
			entities['164'] = '&curren;';
			entities['165'] = '&yen;';
			entities['166'] = '&brvbar;';
			entities['167'] = '&sect;';
			entities['168'] = '&uml;';
			entities['169'] = '&copy;';
			entities['170'] = '&ordf;';
			entities['171'] = '&laquo;';
			entities['172'] = '&not;';
			entities['173'] = '&shy;';
			entities['174'] = '&reg;';
			entities['175'] = '&macr;';
			entities['176'] = '&deg;';
			entities['177'] = '&plusmn;';
			entities['178'] = '&sup2;';
			entities['179'] = '&sup3;';
			entities['180'] = '&acute;';
			entities['181'] = '&micro;';
			entities['182'] = '&para;';
			entities['183'] = '&middot;';
			entities['184'] = '&cedil;';
			entities['185'] = '&sup1;';
			entities['186'] = '&ordm;';
			entities['187'] = '&raquo;';
			entities['188'] = '&frac14;';
			entities['189'] = '&frac12;';
			entities['190'] = '&frac34;';
			entities['191'] = '&iquest;';
			entities['192'] = '&Agrave;';
			entities['193'] = '&Aacute;';
			entities['194'] = '&Acirc;';
			entities['195'] = '&Atilde;';
			entities['196'] = '&Auml;';
			entities['197'] = '&Aring;';
			entities['198'] = '&AElig;';
			entities['199'] = '&Ccedil;';
			entities['200'] = '&Egrave;';
			entities['201'] = '&Eacute;';
			entities['202'] = '&Ecirc;';
			entities['203'] = '&Euml;';
			entities['204'] = '&Igrave;';
			entities['205'] = '&Iacute;';
			entities['206'] = '&Icirc;';
			entities['207'] = '&Iuml;';
			entities['208'] = '&ETH;';
			entities['209'] = '&Ntilde;';
			entities['210'] = '&Ograve;';
			entities['211'] = '&Oacute;';
			entities['212'] = '&Ocirc;';
			entities['213'] = '&Otilde;';
			entities['214'] = '&Ouml;';
			entities['215'] = '&times;';
			entities['216'] = '&Oslash;';
			entities['217'] = '&Ugrave;';
			entities['218'] = '&Uacute;';
			entities['219'] = '&Ucirc;';
			entities['220'] = '&Uuml;';
			entities['221'] = '&Yacute;';
			entities['222'] = '&THORN;';
			entities['223'] = '&szlig;';
			entities['224'] = '&agrave;';
			entities['225'] = '&aacute;';
			entities['226'] = '&acirc;';
			entities['227'] = '&atilde;';
			entities['228'] = '&auml;';
			entities['229'] = '&aring;';
			entities['230'] = '&aelig;';
			entities['231'] = '&ccedil;';
			entities['232'] = '&egrave;';
			entities['233'] = '&eacute;';
			entities['234'] = '&ecirc;';
			entities['235'] = '&euml;';
			entities['236'] = '&igrave;';
			entities['237'] = '&iacute;';
			entities['238'] = '&icirc;';
			entities['239'] = '&iuml;';
			entities['240'] = '&eth;';
			entities['241'] = '&ntilde;';
			entities['242'] = '&ograve;';
			entities['243'] = '&oacute;';
			entities['244'] = '&ocirc;';
			entities['245'] = '&otilde;';
			entities['246'] = '&ouml;';
			entities['247'] = '&divide;';
			entities['248'] = '&oslash;';
			entities['249'] = '&ugrave;';
			entities['250'] = '&uacute;';
			entities['251'] = '&ucirc;';
			entities['252'] = '&uuml;';
			entities['253'] = '&yacute;';
			entities['254'] = '&thorn;';
			entities['255'] = '&yuml;';
		}

		if (useQuoteStyle !== 'ENT_NOQUOTES') {
			entities['34'] = '&quot;';
		}
		if (useQuoteStyle === 'ENT_QUOTES') {
			entities['39'] = '&#39;';
		}
		entities['60'] = '&lt;';
		entities['62'] = '&gt;';

		// ascii decimals to real symbols
		for (decimal in entities) {
			if (entities.hasOwnProperty(decimal)) {
				hash_map[String.fromCharCode(decimal)] = entities[decimal];
			}
		}

		return hash_map;
	}

	function htmlentities(string, quote_style, charset, double_encode) {
		// From: http://phpjs.org/functions
		// +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
		// +    revised by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
		// +   improved by: nobbler
		// +    tweaked by: Jack
		// +   bugfixed by: Onno Marsman
		// +    revised by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
		// +    bugfixed by: Brett Zamir (http://brett-zamir.me)
		// +      input by: Ratheous
		// +   improved by: Rafa. Kukawski (http://blog.kukawski.pl)
		// +   improved by: Dj (http://phpjs.org/functions/htmlentities:425#comment_134018)
		// -    depends on: get_html_translation_table
		// *     example 1: htmlentities('Kevin & van Zonneveld');
		// *     returns 1: 'Kevin &amp; van Zonneveld'
		// *     example 2: htmlentities("foo'bar","ENT_QUOTES");
		// *     returns 2: 'foo&#039;bar'
		var hash_map = this.get_html_translation_table('HTML_ENTITIES', quote_style),
			symbol = '';
		string = string == null ? '' : string + '';

		if (!hash_map) {
			return false;
		}

		if (quote_style && quote_style === 'ENT_QUOTES') {
			hash_map["'"] = '&#039;';
		}

		if (!!double_encode || double_encode == null) {		// TODO: korrekt "== null"? nicht "!= null"?
			for (symbol in hash_map) {
				if (hash_map.hasOwnProperty(symbol)) {
					string = string.split(symbol).join(hash_map[symbol]);
				}
			}
		} else {
			string = string.replace(/([\s\S]*?)(&(?:#\d+|#x[\da-f]+|[a-zA-Z][\da-z]*);|$)/g, function (ignore, text, entity) {
				for (symbol in hash_map) {
					if (hash_map.hasOwnProperty(symbol)) {
						text = text.split(symbol).join(hash_map[symbol]);
					}
				}

				return text + entity;
			});
		}

		return string;
	}

	/* accepts parameters
	 * h  Object = {h:x, s:y, v:z}
	 * OR
	 * h, s, v
	 * This code expects 0 <= h, s, v <= 1
	 * http://stackoverflow.com/a/17243070
	*/
	function HSVtoRGB(h, s, v) {
		var r, g, b, i, f, p, q, t;
		if (h && s === undefined && v === undefined) {
			s = h.s, v = h.v, h = h.h;
		}
		i = Math.floor(h * 6);
		f = h * 6 - i;
		p = v * (1 - s);
		q = v * (1 - f * s);
		t = v * (1 - (1 - f) * s);
		switch (i % 6) {
			case 0: r = v, g = t, b = p; break;
			case 1: r = q, g = v, b = p; break;
			case 2: r = p, g = v, b = t; break;
			case 3: r = p, g = q, b = v; break;
			case 4: r = t, g = p, b = v; break;
			case 5: r = v, g = p, b = q; break;
		}
		return {
			r: Math.floor(r * 255),
			g: Math.floor(g * 255),
			b: Math.floor(b * 255)
		};
	}

	// Returns something between 0..255
	function crc8(message) {
		// return parseInt(CryptoJS.SHA256(message).toString().substr(0,2), 16);
		// return parseInt(CryptoJS.MD5(message).toString().substr(0,2), 16);
		return crc32(message)%256;
	}

	// http://stackoverflow.com/a/2998822
	function pad(num, size) {
		var s = num+"";
		while (s.length < size) s = "0" + s;
		return s;
	}

	function html_rgb(r, g, b) {
		return "#" + pad(r.toString(16), 2) + pad(g.toString(16), 2) + pad(b.toString(16), 2);

	}

	function getToken() {
		return new Date().getTime() + randNum(0, 999999);
	}

	function replaceURLWithHTMLLinks(text) {
		// TODO: deferer?

		// test@example.com
		text = text.replace(/mailto:(.+?)@/ig, "mailto:$1#");
		text = text.replace(/(([^>" ]+?)@([^<" ]+))/ig, "<a href=\"mailto:$1\" target=\"_blank\">$1</a>");
		text = text.replace(/mailto:(.+?)#/ig, "mailto:$1@");

		// www.example.com
		text = text.replace(/:\/\/www\./ig, "://###.");
		text = text.replace(/\b(www\.(.+?))\b/ig, "http://$1");
		text = text.replace(/:\/\/###\./ig, "://www.");

		// http://www.google.com
		// https://stackoverflow.com/questions/37684/how-to-replace-plain-urls-with-links
		var exp = /(\b(https?|ftp|file|mailto):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
		text = text.replace(/href="(.{1,5}?):/ig, "href=\"$1#");
		text = text.replace(exp, "<a href=\"$1\" target=\"_blank\">$1</a>");
		text = text.replace(/href="(.{1,5}?)#/ig, "href=\"$1:");

		return text;
	}

	function luminance_perceived(r, g, b) {
		// Luminance (standard, objective)
		// http://stackoverflow.com/a/596243
		// return (0.2126*r) + (0.7152*g) + (0.0722*b);

		// Luminance (perceived option 1)
		// http://stackoverflow.com/a/596243
		// return (0.299*r) + (0.587*g) + (0.114*b);

		// Luminance (perceived option 2, slower to calculate)
		// http://alienryderflex.com/hsp.html
		// return Math.sqrt(0.299*Math.pow(r,2) + 0.587*Math.pow(g,2) + 0.114*Math.pow(b,2))

		// TODO: Is this the correct formula?
		return Math.sqrt(0.241*Math.pow(r,2) + 0.691*Math.pow(g,2) + 0.068*Math.pow(b,2))
	}

	function colorizeUsername(username, salt) {
		if (salt === undefined) salt = "";

		// return "#" + CryptoJS.SHA256(username).toString().substr(0,6);

		// TODO: is there any method obtaining the background color in runtime? instead of using THEME_DARK

		var x = crc8(username+salt);
		var rgb = HSVtoRGB(x/255, 1, 0.75);

		var bg_lum = <?php if (THEME_DARK) echo 0 /* assume #000011 */; else echo 255 /* assume white */; ?>;
		var fg_lum = luminance_perceived(rgb.r, rgb.g, rgb.b);

		var lum_dif = Math.floor(Math.abs(bg_lum-fg_lum));

		if (lum_dif < 128) {
			<?php if (THEME_DARK) { ?>
			rgb.r = (rgb.r + (128-lum_dif)); if (rgb.r > 255) rgb.r = 255;
			rgb.g = (rgb.g + (128-lum_dif)); if (rgb.g > 255) rgb.g = 255;
			rgb.b = (rgb.b + (128-lum_dif)); if (rgb.b > 255) rgb.b = 255;
			<?php } else { ?>
			rgb.r = (rgb.r - (128-lum_dif)); if (rgb.r < 0) rgb.r = 0;
			rgb.g = (rgb.g - (128-lum_dif)); if (rgb.g < 0) rgb.g = 0;
			rgb.b = (rgb.b - (128-lum_dif)); if (rgb.b < 0) rgb.b = 0;
			<?php } ?>
		}

		return html_rgb(rgb.r, rgb.g, rgb.b);
	}

	// --- Getter and setter

	function encryptionEnabled() {
		return document.getElementById("doencrypt").checked == 1;
	}

	function getPassword() {
		return document.getElementById("pwd").value.trim();
	}

	function autoscrollEnabled() {
		return document.getElementById("autoscroll").checked == 1;
	}

	// --- MD5

	var selftest_md5_finished = false;
	function selftest_md5() {
		if (selftest_md5_finished) return;
		selftest_md5_finished = true;

		var errors = "";
		cmp_a = CryptoJS.enc.Base64.stringify(CryptoJS.MD5("Message"));
		cmp_b = "TCqP5+ryRyHMep8BdRFb1A==";
		if (cmp_a !== cmp_b) {
			errors += "MD5 self test failed!\n";
			console.error("MD5 self test failed: '" + cmp_a + "' vs. '" + cmp_b + "'");
		}

		if (errors) {
			alert(errors+"\nYour browser seems to be buggy. Decryption of particular messages might fail.");
		} else {
			console.info("MD5 self test passed");
		}
	}

	function md5(message) {
		selftest_md5();
		var hash = CryptoJS.MD5(message);
		hash = CryptoJS.enc.Base64.stringify(hash);
		return hash;
	}

	// --- SHA256

	var selftest_sha256_finished = false;
	function selftest_sha256() {
		if (selftest_sha256_finished) return;
		selftest_sha256_finished = true;

		var errors = "";
		cmp_a = CryptoJS.enc.Base64.stringify(CryptoJS.SHA256("Message"));
		cmp_b = "L3dmip37+NWEi57rSnFFypTG7ZI25Kdz9tyvpRMrL5E=";
		if (cmp_a !== cmp_b) {
			errors += "SHA256 self test failed!\n";
			console.error("SHA256 self test failed: '" + cmp_a + "' vs. '" + cmp_b + "'");
		}

		if (errors) {
			alert(errors+"\nYour browser seems to be buggy. Decryption of particular messages might fail.");
		} else {
			console.info("SHA256 self test passed");
		}
	}

	function sha256(message) {
		selftest_sha256();
		var hash = CryptoJS.SHA256(message);
		hash = CryptoJS.enc.Base64.stringify(hash);
		return hash;
	}

	// --- AES

	var selftest_aes_finished = false;
	function selftest_aes() {
		if (selftest_aes_finished) return;
		selftest_aes_finished = true;

		var errors = "";
		var cmp_a = CryptoJS.AES.decrypt("U2FsdGVkX19kJJkA0NL7WJRdXKrdqDcf6A2yDODaL2g=", "Secret Passphrase").toString(CryptoJS.enc.Utf8);
		var cmp_b = "Message";
		if ((cmp_a !== cmp_b) || (aes_dec(aes_enc("Message")) !== "Message")) {
			errors += "AES self test failed!\n";
			console.error("AES self test failed: '" + cmp_a + "' vs. '" + cmp_b + "'");
		}

		if (errors) {
			alert(errors+"\nYour browser seems to be buggy. Decryption of particular messages might fail.");
		} else {
			console.info("AES self test passed");
		}
	}

	function aes_enc(msg, ver) {
		// ver is currently not used
		selftest_aes();
		var passwd = getPassword();
		return CryptoJS.AES.encrypt(msg, roomSalt+passwd);
	}

	function aes_dec(msg, ver) {
		// ver is currently not used
		selftest_aes();
		var passwd = getPassword();
		try {
			return CryptoJS.AES.decrypt(msg, roomSalt+passwd).toString(CryptoJS.enc.Utf8);
		} catch (e) {
			return null;
		}
	}

	// --- Decrypting stuff

	// Alternative hash digest for compatibility issues
	// Some browsers like Jumanji have problems using SHA1 or SHA2, but work with MD5, e.g.
	// Jumanji 1.1.2.4 (versionsangabe nicht sicher) ist inkompatibel mit CryptoJS 3.1.2
	// UA = "Mozilla/5.0 (X11; Linux armv7l) AppleWebKit/534.26+ (KHTML, like Gecko) Version/5.0 Safari/534.26+ jumanji/0.0"
	// CryptoJS.MD5("Message"):
	// - Normal:  4c2a8fe7eaf24721cc7a9f0175115bd4
	// - Jumanji: 4c2a8fe7eaf24721cc7a9f0175115bd4 (OK)
	// CryptoJS.SHA1("Message"):
	// - Normal:  68f4145fee7dde76afceb910165924ad14cf0d00
	// - Jumanji: 5a5aa74ecae1d696900b034d5f1b71497c170ea0 (Error)
	// CryptoJS.SHA256("Message"):
	// - Normal:  2f77668a9dfbf8d5848b9eeb4a7145ca94c6ed9236e4a773f6dcafa5132b2f91
	// - Jumanji: 5a28f8b8778c15a166f7f17ebb89ce8e8381fbb5e39ddc2511239793119a649e (Error)
	// CryptoJS.AES.encrypt("Message", "Secret Passphrase"):
	// - Normal:  U2FsdGVkX19zlzNcfljComkcU0A7XfZ+gzZbI+GyFm0=
	// - Jumanji: U2FsdGVkX19kJJkA0NL7WJRdXKrdqDcf6A2yDODaL2g= (OK)
	// This is a fast version (4x MD5) necessary for slow computers with ARM architecture
	function specialMD5fast4(message) {
		var a = md5("IuErOmVyPeL2ek6e16vkjTWjssgLmd" + message);
		var b = md5("8wxdm3mVi8UQXdboJvCctYwm8ZxTyX" + message);
		return md5(a+b) + md5(b+a);
	}

	function specialHash(val, entrySalt, version) {
		var hash = null;
		if (version == 1) {
			hash = sha256(roomSalt+entrySalt+val);
		} else if (version == 2) {
			hash = specialMD5fast4(roomSalt+entrySalt+val);
		} else {
			console.error("Version " + version + " is unknown at specialHash()");
			return null;
		}
		return entrySalt + "_" + hash + "_ver" + version;
	}

	function cbDecrypt(str, pEntrySalt, pHash, pVer, pMesg, offset, s) {
		var passwd = getPassword();

		var hash_client = specialHash(passwd, pEntrySalt, pVer);
		var hash_server = pEntrySalt + "_" + pHash + "_ver" + pVer;
		if (hash_client == hash_server) {
			var msg = aes_dec(pMesg, pVer);
			msg = htmlentities(msg, "ENT_NOQUOTES", "UTF-8");
			return "<span class=\"encrypted\">" + msg + "</span>";
		} else {
			// TODO: maybe we can still make the string invisible or something, so it becomes more user comfortable?
			// TODO: maybe we can still save the decode-information so we can revert decryption as soon as the password is changed again?
			return str; // can be encrypted later
		}
	}

	function cbUsername(str, pUsername, offset, s) {
		var color = colorizeUsername(pUsername);

		return "<font color=\"" + color + "\"><b>[" + pUsername + "]</b></font>: ";
	}

	function cbDate(str, pDateTime, offset, s) {
		return pDateTime + " - ";
	}

	var formatAndDecryptIsRunning = false; // to prevent that two instances of formatAndDecrypt() run simultaneously
	function formatAndDecrypt() {
		// Set the mutex and write a "Decrypting..." message
		//while (formatAndDecryptIsRunning);
		if (formatAndDecryptIsRunning) return; // If there is already a decrypt process, we will exit instead of waiting (because we don't need 2 subsequent decryptions)
		formatAndDecryptIsRunning = true;
		changeStatusLabel(); // Add "Decrypting..." message
		try {
			var message = document.getElementById("chatmessages").innerHTML;

			// TODO: man darf kein ")" im benutzernamen haben!!!!

			// first decode
			message = message.replace(/\(dec_(.*?)_(.+?)_ver(.+?): (.+?)\)/g, cbDecrypt);

			// then do formating
			message = message.replace(/\(date: (.+?)\)/g, cbDate);
			message = message.replace(/\(user: (.+?)\)/g, cbUsername);

			// make links clickable
			message = replaceURLWithHTMLLinks(message);

			// Only refresh if something has been changed. otherwise we could not copy-paste when it is permanently refreshing
			if (document.getElementById("chatmessages").innerHTML != message) {
				document.getElementById("chatmessages").innerHTML = message;
			}
		} finally {
			// Release the mutex and remove the "Decrypting..." message
			formatAndDecryptIsRunning = false;
			changeStatusLabel(); // Remove "Decrypting..." message
		}
	}

	// --- Status label function

	function alertUser() {
		// TODO: play sound?
	}

	function changeStatusLabel() {
		var status = "";

		if (addMessagePendingQueue.length > 0) {
			status = "Sending (" + addMessagePendingQueue.length + ")...";
		} else if (formatAndDecryptIsRunning) {
			status = "Decrypting...";
		} else if (refreshFailedTimerSet) {
			status = "Checking...";
		} else {
			status = curseq + " posts";
		}

		document.getElementById("status").innerHTML = status;

		var title = ""; // document.title;

		// If tab is not in focus, we might want to alert the user when new posts arrived
		if ((curseq > 0) && (!curVisible) && (backgroundSeqCount != curseq)) {
			blink = 1 - blink;
			title = "(" + (curseq-backgroundSeqCount) + ") " + htmlentities(room);
			if (blink == 1) {
				title = title.toUpperCase();
			} else {
				title = title.toLowerCase();
			}
		} else {
			title = htmlentities(room);
		}
		title += " - " + htmlentities(product);

		if (document.title != title) document.title = title;
	}

	// --- Refresh

	var refreshTimer = null;
	var refreshTimerSet = false;
	var refreshFailedTimer = null;
	var refreshFailedTimerSet = false;

	function refresh_failed(id) {
		sajax_cancel(id);

		// var refreshFailedTimerSet = (typeof refreshFailedTimer !== 'undefined');
		// var refreshFailedTimerSet = (refreshFailedTimer !== null);
		if (refreshFailedTimerSet) {
			clearTimeout(refreshFailedTimer);
			refreshFailedTimer = null;
			refreshFailedTimerSet = false;
		}

		// Remove "Checking..." message
		// changeStatusLabel();

		// TODO: die meldung kommt viel zu oft, auch nachdem alles wieder geht. warum?
//		alert("Refresh failed. Will try again.");
		refresh();
	}

	function refresh_cb(data) {
		// var refreshFailedTimerSet = (typeof refreshFailedTimer !== 'undefined');
		// var refreshFailedTimerSet = (refreshFailedTimer !== null);
		if (refreshFailedTimerSet) {
			clearTimeout(refreshFailedTimer);
			refreshFailedTimer = null;
			refreshFailedTimerSet = false;
		}

		var newcurseq = data[0];
		var new_data  = data[1];
		var userstats = data[2];

		// User list
		document.getElementById("activeusers").innerHTML = '<b>ACTIVE USERS:</b><br><br>';
		for (var i = 0; i < userstats.length; ++i ) {
			var username = userstats[i]['USERNAME'];
			var color    = colorizeUsername(username);
			var postfix  = (userstats[i]['UNIQUEID'] == uniqueID) ? ' (you)' : '';
			document.getElementById("activeusers").innerHTML += '<font color="'+color+'"><b>'+htmlentities(username)+'</b></font>'+postfix+'<br>';
		}

		var timeout = <?php echo TIMER_1; ?>; // default timeout
		if (newcurseq != curseq) {
			curseq = newcurseq;

			document.getElementById("chatmessages").innerHTML += new_data;

			formatAndDecrypt();

			if (autoscrollEnabled()) {
				scrollToBottom();
			}

			if (!curVisible) {
				// blink = 0;
				alertUser();
			}

			timeout = <?php echo TIMER_2; ?>; // shorter timeout when a new message has just arrived
		}

		// Remove "Checking..." message
		changeStatusLabel();

		if (!refreshTimerSet) {
			refreshTimer = setTimeout("refresh()", timeout);
			refreshTimerSet = true;
		}
	}

	function refresh() {
		// Only run the timer once (since we also call refresh() when a new post is added)
		// var refreshTimerSet = (typeof refreshTimer !== 'undefined');
		// var refreshTimerSet = (refreshTimer !== null);
		if (refreshTimerSet) {
			clearTimeout(refreshTimer);
			refreshTimer = null;
			refreshTimerSet = false;
		}

		var sajax_id = x_refresh(room, curseq, user, uniqueID, refresh_cb);

		// TODO: gibt es eine bessere möglichkeit festzustellen, ob der request gestorben ist? ein negativer callback z.B.?
		if (!refreshFailedTimerSet) {
			refreshFailedTimer = setTimeout(function() { refresh_failed(sajax_id); }, <?php echo TIMER_DEAD; ?>);
			refreshFailedTimerSet = true;
		}

		// Add "Checking..." message
		changeStatusLabel();
	}

	// --- Add

	var addMessagePendingQueue = [];

	function do_encrypt(msg) {	// NG: "Encrypting" status message
		var msgToServer = "";

		var lines = msg.split("\n");
		for (var i = 0; i < lines.length; ++i ) {
			var line = lines[i];

			// encrypt message
			if (encryptionEnabled()) {
				var version   = <?php echo CFG_CIPHERSUITE; ?>;
				var passwd    = getPassword();
				var entrySalt = randomString(20);
				var hash      = specialHash(passwd, entrySalt, version);
				line = "(dec_" + hash + ": " + aes_enc(line, version) + ")";
			}

			msgToServer += line + "\n";
		}

		return msgToServer.trim();
	}

	function add_get_index_of(token) {
		for (var i = 0; i < addMessagePendingQueue.length; ++i ) {
			if (addMessagePendingQueue[i].token == token) return i;
		}
		return -1;
	}

	function add_failed(token) {
		var i = add_get_index_of(token);
		if (i == -1) return;

		sajax_cancel(addMessagePendingQueue[i].sajax_id);

		var failedMsg = addMessagePendingQueue[i].message;
		addMessagePendingQueue.remove(i);
		changeStatusLabel(); // Remove "Sending..." message

		// Nachricht ausgeben.
		// QUE TODO: automatisch neu versuchen?
		// TODO: reihenfolge der nachrichten stimmt nicht.
		alert("Following message could not be sent. Please try again:" + failedMsg);

		// Add message back to the input box, so the user can send it again
		if (document.getElementById("line").value.trim() == initMsg.trim()) {
			// If login message fails, then clear box first before append
			document.getElementById("line").value = "";
		}
		var newMsgCont = document.getElementById("line").value.trim() + "\n" + failedMsg.trim();
		document.getElementById("line").value = newMsgCont.trim();
	}

	function add_cb(data) {
		var token = data[0];

		var i = add_get_index_of(token);
		if (i == -1) return;

		clearTimeout(addMessagePendingQueue[i].failTimer);
		addMessagePendingQueue.remove(i);
		changeStatusLabel(); // Remove "Sending..." message

		// Refresh now, so that the new post quickly appears!
		refresh();
	}

	var addIsRunning = false; // to prevent that two instances of add() run simultaneously
	function add() {
		// Set the mutex
		while (addIsRunning);
		addIsRunning = true;
		try {
			var msg = document.getElementById("line").value.trim();
			if ((msg == "") || (msg == initMsg.trim())) {
				return;
			} else {
				document.getElementById("line").value = "";
			}

			send_msg(msg, 1);

			sleep(100); // damit es zu keiner racecondition (= posts in falscher reihenfolge) kommen kann
		} finally {
			// Release the mutex
			addIsRunning = false;
		}
	}

	function send_msg(msg, encrypt) {
		var msgToServer = msg;

		if (encrypt) {
			msgToServer = do_encrypt(msgToServer);
		}

		// We need a token which is sent back by the server, so our succeed-callback can find and remove the entry from the queue.
		// We cannot use the sajax_id generated by SAJAX, because we need to send it to the server.
		var token = getToken();
		var sajax_id = x_add_line(token, room, user, msgToServer, add_cb);

		// Backup the text, so we can back it up if it fails
		// TODO: gibt es eine bessere möglichkeit festzustellen, ob der request gestorben ist? ein negativer callback z.B.?
		var t = setTimeout(function() { add_failed(token); }, <?php echo TIMER_DEAD; ?>);
		addMessagePendingQueue.push({
			"token": token,
			"sajax_id": sajax_id,
			"message": msg,
			"failTimer": t
		} );
		changeStatusLabel(); // Add "Sending..." message

		return sajax_id;
	}

	// --- Logoff

	var logoffEventFired = 0;
	function send_logoff() {
		if (logoffEventFired == 1) return;
		logoffEventFired = 1;

		send_msg("(Logging off)", 0);

		document.getElementById("pwd").value = "";

		// TODO: go back to login form
	}

	var logonEventFired = 0;
	function send_logon() {
		if (logonEventFired == 1) return;
		logonEventFired = 1;

		send_msg("(Logging in)", 0);
	}

	// --- Initialization stuff

	var initMsg = "(enter your message here)"; /* const */

	function initShowHideHandlers() {
		// https://stackoverflow.com/questions/1060008/is-there-a-way-to-detect-if-a-browser-window-is-not-currently-active

		var hidden = "hidden";

		// Standards:
		if (hidden in document)
			document.addEventListener("visibilitychange", onchange);
		else if ((hidden = "mozHidden") in document)
			document.addEventListener("mozvisibilitychange", onchange);
		else if ((hidden = "webkitHidden") in document)
			document.addEventListener("webkitvisibilitychange", onchange);
		else if ((hidden = "msHidden") in document)
			document.addEventListener("msvisibilitychange", onchange);
		// IE 9 and lower:
		else if ('onfocusin' in document)
			document.onfocusin = document.onfocusout = onchange;
		// All others:
		else
			window.onpageshow = window.onpagehide = window.onfocus = window.onblur = onchange;

		function onchange(evt) {
			var v = true, h = false,
			evtMap = {
				focus:v, focusin:v, pageshow:v, blur:h, focusout:h, pagehide:h
			};

			evt = evt || window.event;
			var res;
			if (evt.type in evtMap) {
				res = evtMap[evt.type];
			} else {
				res = !this[hidden];
			}

			if (res) {
				onChatShow();
			} else {
				onChatHide();
			}
		}
	}

	function initReturnKeyHandler() {
		var wage = document.getElementById("line");
		wage.addEventListener("keydown", function (e) {
			if (e.which == 13 || e.keyCode === 13) {  //checks whether the pressed key is "Enter"
				add();
			}
		});
		// important for <textarea> otherwise we'll have a #13 in the box after it
		wage.addEventListener("keyup", function (e) {
			if (e.which == 13 || e.keyCode === 13) {  //checks whether the pressed key is "Enter"
				document.getElementById("line").value = "";
			}
		});
	}

	function initUnloadHandler() {
		// TODO: does not work when following a link or when clicking "back button"
		window.onbeforeunload = function() {
			send_logoff();
		}
		window.addEventListener("beforeunload", function(e) {
			send_logoff();
		}, false);
	}

	// Delayed (single run) onkeydown event for the password field
	var delayedKeyChangeTimer = null;
	var delayedKeyChangeTimerSet = false;
	function initKeyChangeHandler() {
		// We use onkeydown instead of onkeypress, otherwise "del" or "ctrl+v" won't work on Chrome
		// See http://help.dottoro.com/ljlwfxum.php

		document.getElementById("pwd").onkeydown = function(evt) {
			var enterPressed = (evt.which == 13 || evt.keyCode === 13);

			// var delayedKeyChangeTimerSet = (typeof delayedKeyChangeTimer !== 'undefined');
			// var delayedKeyChangeTimerSet = (delayedKeyChangeTimer !== null);
			if (delayedKeyChangeTimerSet) {
				clearTimeout(delayedKeyChangeTimer);
				delayedKeyChangeTimer = null;
				delayedKeyChangeTimerSet = false;
			}

			var timeout = enterPressed ? 0 : <?php echo TIMER_KEYCHANGE; ?>;
			if (!delayedKeyChangeTimerSet) {
				delayedKeyChangeTimer = setTimeout("formatAndDecrypt()", <?php echo TIMER_KEYCHANGE; ?>);
				delayedKeyChangeTimerSet = true;
			}
		};
	}

	function initPage() {
		uniqueID=randomString(20);
		// document.getElementById("pwd").value  = "";
		document.getElementById("line").value = initMsg;
		changeStatusLabel();
		initUnloadHandler();
		initReturnKeyHandler();
		initShowHideHandlers();
		initKeyChangeHandler();
		refresh();
		// We do it at last, otherwise we will have the problem that the chat will not be scrolled to the very bottom in the beginning
		send_logon();
	}

	// --- Misc event handlers

	function onChatShow() {
		curVisible = true;
		changeStatusLabel();
	}

	function onChatHide() {
		curVisible = false;
		blink = 1;
		backgroundSeqCount = curseq;
		// changeStatusLabel();
	}

	function onFocusChatField() {
		if (document.getElementById("line").value == initMsg) {
			document.getElementById("line").value = "";
		}
	}

	</script>

</head>

<body class="chatroom" onload="initPage();">

<div id="header">
	<font size="+2"><font color="orange"><?php echo htmlentities(PRODUCT_NAME); ?> <font size="-1"><?php
		echo htmlentities(MCC_VER);
	?></font></font> <span class="userid">[<?php
		echo htmlentities("$ses_user@$ses_room");
	?>]</span></font><br>
	<span class="pwdenter">Password for client-side encryption of messages: <input type="password" name="pwd" id="pwd" value=""></span>
	<span class="logout"><a href="<?php echo $_SERVER['SCRIPT_NAME']; ?>" onClick="send_logoff();return true;">Back to login form</a></span>
</div>
<div id="status"><em>Loading...</em></div>
<div id="activeusers"></div>
<div id="chatmessages"></div>
<form name="f" action="#" onsubmit="add();return false;">
	<div id="postarea">
		<textarea cols="70" rows="3" name="line" id="line" value="" onfocus="onFocusChatField()"></textarea>
		<input type="button" name="check" value="Post message" onclick="add();return false;" class="button">
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td align="left" width="50%"><label><input type="checkbox" id="autoscroll" name="autoscroll" value="yes" checked> Scroll down when new message arrives</label><br></td>
				<td align="left" width="50%"><label><input type="checkbox" id="doencrypt"  name="doencrypt"  value="yes" checked> Encrypt all messages</label><br></td>
			</tr>
		</table>
	</div>
</form>

</body>

<?php

} else if ($show_list) {

?>

</head>

<body>

<h1><?php echo htmlentities(PRODUCT_NAME); ?> <?php echo htmlentities(MCC_VER); ?></h1>

<h2>List chat rooms</h2>

<table border="1" cellpadding="4" cellspacing="0">
<thead><tr>
	<td>Chat room</td>
	<td>Messages</td>
	<td>Users</td>
	<td>Last activity</td>
</tr></thead>
<tbody>
	<?php

	$rooms = list_rooms();
	foreach ($rooms as &$room) {
		$room_file = chatroom_file($room);
		$messages = count_messages($room);
		$users = count_users($room);
		$last_activity = date('Y-m-d H:i:s o', filemtime($room_file));
		echo '<tr>';
		echo '<td>'.htmlentities($room).'</td>';
		echo '<td>'.htmlentities($messages).'</td>';
		echo '<td>'.htmlentities($users).'</td>';
		echo '<td>'.htmlentities($last_activity).'</td>';
		echo '</tr>';
	}

	?>
</tbody>
</table>

<p><a href="<?php echo $_SERVER['SCRIPT_NAME']; ?>">Back to login</a></p>

<?php

} else {

?>

</head>

<body>

<h1><?php echo htmlentities(PRODUCT_NAME); ?> <?php echo htmlentities(MCC_VER); ?></h1>

<h2>Login</h2>

<form action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" method="POST">
<input type="hidden" name="sent" value="1">

<?php

if (isset($_POST['sent'])) {
	if ($ses_room == '') {
		echo "<p><span class=\"error\"><b>Error:</b> You must enter a chat room name.</span></p>";
	} elseif (!$chat_exists) {
		echo "<p><span class=\"error\"><b>Error:</b> Chat room <b>".htmlentities($ses_room)."</b> does not exist. Please ask an administrator to create one.</span></p>";
	}
}

?>

Room<?php if (CHAT_MUST_EXIST) echo " (chat room must exist)"; ?>:<br>
<input type="text" name="ses_room" value="<?php echo $ses_room; ?>"><br><br>

<?php

if (isset($_POST['sent'])) {
	if ($ses_user == '') {
		echo "<p><span class=\"error\"><b>Error:</b> You must enter an username.</span></p>";
	}
}

?>

Username (you can freely choose it yourself):<br>
<input type="text" name="ses_user" value="<?php echo $ses_user; ?>"><br><br>

<input type="submit" value="Login" class="button">

<?php
if (CHAT_MUST_EXIST) {
	if (isset($_POST['sent'])) {
		if (($admin_pwd != '') && (!adminAuth($admin_pwd, ACTION_CREATE_ROOM))) {
			echo "<p><span class=\"error\"><b>Error:</b> This is not the correct administrator password.</span></p>";
		}
	}
	?>
	<br><br><br>Optional: Admin-Password for creating a new chat:<br>
	<input type="password" name="admin_pwd" value=""><br><br>
	<?php
}
?>

</form>

<hr>

<h2>List chat rooms</h2>

<form action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" method="POST">
<input type="hidden" name="list_chatrooms" value="1">

<?php
if (LIST_CHATS_REQUIRE_PWD) {
	if (isset($_POST['list_chatrooms'])) {
		echo "<p><span class=\"error\"><b>Error:</b> Wrong password.</span></p>";
	}
	?>
	Admin-Password for listing chats:<br>
	<input type="password" name="admin_pwd" value=""><br><br>
	<?php
}
?>

<input type="submit" value="List chat rooms" class="button"><br><br>

</form>

<hr>

<p><a href="http://www.viathinksoft.com/redir.php?id=324897" target="_blank"><?php echo htmlentities(PRODUCT_NAME); ?> <?php echo htmlentities(MCC_VER); ?></a> &copy; 2014-2018 <a href="http://www.viathinksoft.com/" target="_blank">ViaThinkSoft</a>.</a></p>

</body>

<?php
}
?>

</html>

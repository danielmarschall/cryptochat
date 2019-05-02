<?php

// ViaThinkSoft CryptoChat
// (C) 2014 by Daniel Marschall, ViaThinkSoft
// Licensed under the terms of GPLv3

// -----------------------------------------------------------------------------------------------

require __DIR__ . '/../config/config.inc.php';
define('PRODUCT_NAME', 'CryptoChat');
define('MCC_VER',      trim(file_get_contents(__DIR__ . '/../VERSION')));

header('Content-type: text/html; charset=utf-8');

?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
	"http://www.w3.org/TR/html4/loose.dtd">

<html>

<head>
	<META HTTP-EQUIV="content-type" CONTENT="text/html; charset=utf-8">
	<title><?php echo PRODUCT_NAME; ?> - Manually encrypt chat lines</title>

	<script type="text/javascript" src="../<?php echo DEP_DIR_CRYPTOJS; ?>/rollups/sha256.js"></script>
	<script type="text/javascript" src="../<?php echo DEP_DIR_CRYPTOJS; ?>/rollups/sha1.js"></script>
	<script type="text/javascript" src="../<?php echo DEP_DIR_CRYPTOJS; ?>/rollups/aes.js"></script>
	<script type="text/javascript" src="../<?php echo DEP_DIR_CRYPTOJS; ?>/rollups/md5.js"></script>

	<script type="text/javascript">

	var roomSalt = null;

	// http://stackoverflow.com/questions/1349404/generate-a-string-of-5-random-characters-in-javascript/1349426#1349426
	function randomString(len) {
		var text = "";
		var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

		for( var i=0; i < len; i++ ) {
			text += possible.charAt(Math.floor(Math.random() * possible.length));
		}

		return text;
	}

	// --------------------------------------------

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
			console.info("AES self test passed");
		}
	}

	function md5(message) {
		selftest_md5();
		var hash = CryptoJS.MD5(message);
		hash = CryptoJS.enc.Base64.stringify(hash);
		return hash;
	}

	// --- SHA1

	var selftest_sha1_finished = false;
	function selftest_sha1() {
		if (selftest_sha1_finished) return;
		selftest_sha1_finished = true;

		var errors = "";
		cmp_a = CryptoJS.enc.Base64.stringify(CryptoJS.SHA1("Message"));
		cmp_b = "aPQUX+593navzrkQFlkkrRTPDQA=";
		if (cmp_a !== cmp_b) {
			errors += "SHA1 self test failed!\n";
			console.error("SHA1 self test failed: '" + cmp_a + "' vs. '" + cmp_b + "'");
		}

		if (errors) {
			alert(errors+"\nYour browser seems to be buggy. Decryption of particular messages might fail.");
		} else {
			console.info("SHA1 self test passed");
		}
	}

	function sha1(message) {
		selftest_sha1();
		var hash = CryptoJS.SHA1(message);
		hash = CryptoJS.enc.Base64.stringify(hash);
		return hash;
	}

	function sha1_base16(message) {
		selftest_sha1();
		return CryptoJS.SHA1(message).toString();
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
			console.info("AES self test passed");
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

	# ---

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

	// --------------------------------------------

	function getPassword() {
		return document.getElementById("key").value;
	}

	function cbLine(str, p1, p2, p3, offset, s) {
		var ver       = document.getElementById("ver").value;
		var passwd    = document.getElementById("key").value;
		var entrySalt = randomString(20);
		var hash      = specialHash(passwd, entrySalt, ver);

		return "(date: "+p1+")(user: "+p2+")(dec_"+hash+": "+aes_enc(p3,ver)+")<br>\n";
	}

	function convert() {
		var ses_room = document.getElementById("room").value;
		var xsalt    = document.getElementById("xsalt").value;
		roomSalt = sha1_base16(xsalt + ses_room);

		var message = document.getElementById("in").value + "\n";
		message = message.replace(/(.+?) - \[(.+?)\]: (.+?)\n/g, cbLine);
		document.getElementById("out").value = message;
	}

	function initPage() {
		document.getElementById("out").value = "";
	}
</script>

</head>

<body onload="initPage();">

	<h1>Manually encrypt chat lines</h1>

	<table border="0" cellpadding="5" cellspacing="0">
	<tr>
		<td>Server's X_SALT: </td>
		<td><input style="width:500px" id="xsalt" value="<?php if (!KEEP_X_SALT_SECRET) echo X_SALT; ?>"></td>
	</tr><tr>
		<td>Algorithm: </td>
		<td><select id="ver">
			<option value="1"<?php if (CFG_CIPHERSUITE == 1) echo ' selected'; ?>>[1] SHA256</option>
			<option value="2"<?php if (CFG_CIPHERSUITE == 2) echo ' selected'; ?>>[2] specialMD5fast4</option>
		</select></td>
	</tr><tr>
		<td>Key: </td>
		<td><input style="width:500px" id="key"></td>
	</tr><tr>
		<td>Chatroom name: </td>
		<td><input style="width:500px" id="room"></td>
	</tr><tr>
		<td colspan="2">Input:<br>
			<textarea id="in" cols="100" rows="6">2014-12-31 14:21:20 - [Daniel]: Hello world!
2014-12-31 14:21:22 - [Daniel]: Example!</textarea><br></td>
	</tr><tr>
		<td colspan="2"><input type="button" onclick="convert();" value="Convert"><br></td>
	</tr><tr>
		<td colspan="2">Output:<br>
			<textarea id="out" cols="100" rows="6"></textarea></td>
	</tr>
	</table><br>
</body>

</html>


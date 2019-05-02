<?php

define('X_SALT',                 '<enter something secret here>'); // If you change this, existing chats will become unreadable.
define('KEEP_X_SALT_SECRET',     true); // More secure. Only applies to manual_create.php
define('CHAT_MUST_EXIST',        true); // If set to false, users can create chat rooms themselves.
define('PWD_LIST_ROOMS',         '');   // Password for listing chat rooms
define('LIST_CHATS_REQUIRE_PWD', true); // If set to true, you need a password for listing all chat rooms.
define('PWD_CREATE_ROOM',        '');   // Password for creating new chat rooms
define('REQUIRE_TLS',            true); // If set to true, you can only connect via HTTPS.
define('TIMER_1',                2500); // Default reload timer (ms)
define('TIMER_2',                1000); // Reload timer (ms) when a new message arrived
define('TIMER_KEYCHANGE',        1000); // Timer when the key edit was changed
define('TIMER_DEAD',             5000); // Max timeout until AJAX request is seen as failed
define('THEME_DARK',             true); // Please set to true if you use a dark theme (important for automatical username coloring)
define('USERSTAT_INACTIVE_SECS', 10);   // After how many seconds will the user be removed from the online list?
define('DELETE_OLD_USERSTATS',   true); // Delete user stats when the activity is above USERSTAT_INACTIVE_SECS?

// Default cipher suite for sending new messages. Note that messages sent with another cipher suite still will be decrypted using the original algorithm.
// ----+-------+----------------------------------------------------------------
// Num | Since | Description
// ----+-------+----------------------------------------------------------------
// 1   | 0.9   | AES Encryption. Salted SHA256 hash, AES encryption.
// 2   | 0.9   | AES Encryption. Salted&Cascaded MD5 because of SHA256 compatibility issues with OpenPandora's web browser.
define('CFG_CIPHERSUITE',    2);

// CryptoJS
// Minimum required version: 3.1.2
// Get it here: http://code.google.com/p/crypto-js/
define('DEP_DIR_CRYPTOJS', 'dependencies/cryptojs');

// SAJAX
// Minimum required version: 0.13
// Get it here: https://github.com/AJenbo/Sajax/
define('DEP_DIR_SAJAX', 'dependencies/sajax');

// JSONWrapper
// Get it here: http://www.boutell.com/scripts/jsonwrapper.html
define('DEP_DIR_JSONWRAPPER', 'dependencies/jsonwrapper');

// CRC32 JS PHP
// Get it here: https://github.com/wbond/crc32-js-php
define('DEP_DIR_CRC32', 'dependencies/crc32-js-php');

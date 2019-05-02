#!/bin/bash

DIR=$( dirname "$0" )

if [ ! -d "$DIR"/chats ]; then
	mkdir "$DIR"/chats

	# TODO: is 1777 ok?
	chmod 1777 "$DIR"/chats
fi

if [ ! -f "$DIR"/chats/.htaccess ]; then
	(
		echo "Order Deny,Allow"
		echo "Deny From All"
	) > "$DIR"/chats/.htaccess
fi

if [ ! -d "$DIR"/dependencies ]; then
	mkdir "$DIR"/dependencies
	cd "$DIR"/dependencies

	wget http://www.boutell.com/scripts/jsonwrapper.tar.gz
	tar -zxvf jsonwrapper.tar.gz
	rm jsonwrapper.tar.gz

	wget https://crypto-js.googlecode.com/files/CryptoJS%20v3.1.2.zip
	mkdir cryptojs
	unzip -d cryptojs/ "CryptoJS v3.1.2.zip"
	rm "CryptoJS v3.1.2.zip"

	wget https://github.com/AJenbo/Sajax/archive/master.zip
	unzip master.zip
	rm master.zip
	mv Sajax-master sajax

	mkdir crc32-js-php
	cd crc32-js-php
	wget https://raw.github.com/wbond/crc32-js-php/master/crc32.js
fi

if [ ! -f "$DIR"/config/config.inc.php ]; then
	cp "$DIR"/config/config.original.inc.php "$DIR"/config/config.inc.php
	nano "$DIR"/config/config.inc.php
fi

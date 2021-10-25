# ViaThinkSoft CryptoChat
Cryptochat an AJAX crypto chat system which encrypts and decrypts using JavaScript on client side.
The chat log is saved encrypted on the server.

## Note about security:
- Please ensure that you are using HTTPS, otherwise the JavaScript could be manipulated using a Man-In-The-Middle attack.

## What is encrypted?
- Messages which are shown ORANGE are encrypted. White messages are not encrypted.
- Usernames and time stamps are not encrypted.
- Chat room names are not encrypted.

## Requirements
**Server:**
- Linux
- PHP
- Apache

**Clients:**
- JavaScript capable web browser

## How to install CryptoChat
To install CryptoChat, run the script **install.sh**.
If you cannot run the script, then copy **config/config.original.inc.php** to **config/config.inc.php** and edit it. After that, please make sure that the folder "chats" has the correct chmod permission so that the webserver daemon can write to it.

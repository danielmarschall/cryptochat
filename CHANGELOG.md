# Changelog

0.26 Beta (2023-12-29)
- Fixed PHP incompatibility in dependency "JSON Wrapper"

0.25 Beta (2018-04-18)
- Fixed small PHP warning
- Black background is now darkblue

0.24 Beta (2014-03-01)
- New feature: User online list
  Added configuration flags: USERSTAT_INACTIVE_SECS and DELETE_OLD_USERSTATS
- fixed compatibility problem with Chrome
- smaller fixes

0.23.1 beta (2014-02-23)
- fixed compatibility problem with Chrome

0.23 beta (2014-02-21)
- Decryption will start without delay if enter is pressed
- improved page title line (room name is now in front)
- htmlentities() to prevent XSS on username and roomname
- smaller fixes

0.22 beta (2014-02-17)
- New feature: List chat rooms (requires password)
- Improved username coloring function
- Username, Chat name and admin password will now be trimmed
- Chat names are now automatically converted to lowercase
- Added config symbol THEME_DARK
- Added config symbol CFG_CIPHERSUITE (replaces USE_MD5_COMPAT)
- Renamed config symbol ADMIN_PASSWORD to PWD_CREATE_ROOM
- Added config symbol PWD_LIST_ROOMS
- Added config symbol LIST_CHATS_REQUIRE_PWD
- Renamed config symbol NEEDS_TLS to REQUIRE_TLS
- Small bug fixes

0.21 beta (2014-02-16)
- First public release


---
title: configuration
---
# NacoWiki configuration


- umask (int) - Optional umask
- proxy-ips (array|string) : list of IP addresses of trusted reverse proxies
  Either as an array of strings, or as string with comma separated IP addresses.
- no_cache (bool) : defaults to false.  If set to true, enables
  sending of headers that disable browser caches.
- plugins_path (array|string) : list of directories where to look for plugins. \
  Defaults to `APP_DIR/plugins`. \
  It can be specified as an array of strings, or as single array with
  paths separated by PHP's PATH_SEPARATOR `:` under Linux, `;` under Windows.
- base_url (string) - Base Application URL \
  Normally defaults to the HTTP SCRIPT_NAME value.  However, it can
  be changed for placing after a reverse proxy which changes the path.
- static_url (string) - URL Path to static resources \
  Normally defaults to the dirname of HTTP SCRIPT_NAME.  However, it can
  be changed for placing after a reverse proxy which changes the path. \
  It has to be a HTTP path that can reach the static resources.
- static_path (string) - File Path to static resources \
  Path in the filesystem to reach the actual static resources.  Defaults
  to `APP_DIR/assets`.
- file_store (string) -  Location where data files are stored \
  Defaults to `CWD/files`.  These files do *NOT* need to be served
  by the web server, so that the PHP script can control all access
  to them.
- read_only (bool|string) - if true, makes the wiki read-only.  Defaults to false. \
  if set to `not-auth`, it will make it read-only until the user
  authenticates.
- unix_eol (bool) - force all input files to be converted from CRLF to UNIX style
  LF for the EOL marker.  Defaults to true.  Set to false if you want
  to keep CRLF as EOL markers.
- default_doc (string) - the default document for browsing into folders. \
  defaults to `index.md`.
- plugins (array) - Plugin configuration \
  Plugins can read its own configuration from this array. By
  convention, they should read only from the keys matching the
  plugin name.  In addition, you can enable or disable
  plugins with the keys:
  - enabled (string|array) - list of plugins to enable either as a
    string with comma separated name of plugins or as an array with
    plugin names.
  - disabled (string|array) - list of plugins to disable either as a
    string with comma separated name of plugins or as an array with
    plugin names.
  By default all found plugins will be enabled.
- cookie_id (string) - used to keep cookies unique
- cookie_age (int) - seconds to keep the cookies alive. \
  Defaults to 30 days.
- ext_url (string) - URL used in the logo link.  Defaults to
  the current http host home page.
- title (string) - Window title bar
- copyright (string) - copyright text show in the footer
- theme (string) - Overall nacowiki theme
- theme-highlight-js (string) - theme to be used for syntax highlighting
- theme-codemirror (string) - CodeMirror theme

***
* nacowiki.php,39
***


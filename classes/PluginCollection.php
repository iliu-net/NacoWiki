<?php
/**  @package NWiki */
namespace NWiki;

class PluginCollection {
  const OK = false;
  const OKSTOP = true;
  const NOOP = NULL;
  const API_OK = 'ok';
  const API_ERROR = 'error';

  static $stack = [[]];		// Used for stack events
  static $handlers = [];	// Event handlers
  static $mediatypes = [];	// Used to quickly identified registered handlers
  static $plugins = [];		// List of loaded plugins

  static function pluginPath(array $cfg) : array {
    $path = $cfg['plugins_path'] ?? APP_DIR . 'plugins';
    if (!is_array($path)) $path = explode(PATH_SEPARATOR, $path);
    return $path;
  }
  static function path(string $file = NULL) : ?string {
    $trace = debug_backtrace();
    $src = $trace[0]['file'];
    if (strtolower(substr($src,-4)) == '.php') $src = substr($src,0,-4);
    if (!is_dir($src)) $src = dirname($trace[0]['file']);
    $src .= '/';
    if ($file) $src .= ltrim($file,'/');
    return $src;
  }
  static function apiError(array &$ev, string $msg, array $opts = [ 'status' => self::API_ERROR, 'rc' => self::OK ]) : ?bool {
    $ev['status'] = $opts['status'];
    $ev['msg'] = $msg;
    return $opts['rc'];
  }

  static function loadPlugins(array $cfg) : array {
    $path = self::pluginPath($cfg);
    //~ echo('TRC:'.__FILE__.','.__LINE__.':path:');print_r($path);

    $plugins = [];

    foreach ($path as $plug_dir) {
      //~ echo('TRC:'.__FILE__.','.__LINE__.':plugdir:'.$plug_dir.PHP_EOL);
      $plugs = glob(rtrim($plug_dir,'/').'/*.php');
      //~ echo('TRC:'.__FILE__.','.__LINE__.':plugs:');print_r($plugs);
      if ($plugs === false) continue;
      foreach ($plugs as $plugin_file) {

	if (!empty($plugins[$plugin_file])) continue;
	$plugins[$plugin_file] = []; // Make sure it exists!

	$plugin_mod = basename($plugin_file, '.php');
	if (isset($cfg['plugins']['enabled'])) {
	  if (!is_array($cfg['plugins']['enabled']))
	    $cfg['plugins']['enabled'] = preg_split('/\s*,\s*/',trim($cfg['plugins']['enabled']));
	  if (!in_array($plugin_mod, $cfg['plugins']['enabled'])) continue;
	}
	if (isset($cfg['plugins']['disabled'])) {
	  if (!is_array($cfg['plugins']['disabled']))
	    $cfg['plugins']['disabled'] = preg_split('/\s*,\s*/',trim($cfg['plugins']['disabled']));
	  if (in_array($plugin_mod, $cfg['plugins']['disabled'])) continue;
	}

	$classes = get_declared_classes();
	require_once $plugin_file;
	$loaded = array_diff(get_declared_classes(), $classes);

	foreach ($loaded as $class_name) {
	  $loader = [ $class_name, 'load' ];
	  if (!is_callable($loader)) continue;
	  $loader($cfg);
	  $plugins[$plugin_file][$class_name] = $class_name;
	  //~ self::$plugins[basename($plugin_file,'.php')] = $plugin_file;
	  self::$plugins[$class_name] = $plugin_file;
	}
      }
    }
    return $plugins;
  }
  static function &event(array $content = []) : array {
    if (count($content) == 0) return self::$stack[0];
    $c = count(self::$stack);
    self::$stack[$c] = $content;
    return self::$stack[$c];
  }
  static function registerEvent(string $event, $callable) : void {
    if (!isset(self::$handlers[$event])) self::$handlers[$event] = [];
    self::$handlers[$event][] = $callable;
  }
  static function dispatchEvent(\NacoWikiApp $wiki, $event, array &$data) : bool {
    // RETURNS
    // true: handled, false: not-handled

    if (!isset(self::$handlers[$event])) return false;
    $ret = false;
    foreach (self::$handlers[$event] as $callme) {
      $c = $callme($wiki,$data);
      if (is_null($c)) continue;	// NULL, it wasn't handled
      if ($c) return true;		// TRUE, was handled, and should stop dispatching
      $ret = true;			// FALSE, was handled, but should continue dispatching
    }
    return $ret;
  }
  /**
   * @param string|array $ext -- file extension or array listing file extensions
   * @param string $class -- class name handling this media type
   * @param NULL|string|array $handler -- how media types are handled
   */
  static function registerMedia($ext,string $class, $handler = NULL) : void {
    $media_handlers = [ 'view', 'read', 'render', 'layout', 'missing', 'edit', 'save', 'preSave', 'postSave' ];
    // view: {ext} | core
    //  read: {ext} | core
    //  render: {ext} | core
    //  layout: {ext} | core
    // missing: {ext} | core
    // edit: {ext} | core
    // preSave: (ext) |  core
    // save: {ext} | core

    // Additional:
    // 	pre-render:$ext
    // 	pre-render
    // 	post-render:$ext
    // 	post-render

    //~ static function view(\NacoWikiApp $wiki, array &$data) : ?bool { }
    //~ static function read(\NacoWikiApp $wiki, array &$data) : ?bool { }
    //~ static function render(\NacoWikiApp $wiki, array &$data) : ?bool { }
    //~ static function layout(\NacoWikiApp $wiki, array &$data) : ?bool { }
    //~ static function missing(\NacoWikiApp $wiki, array &$data) : ?bool { }
    //~ static function edit(\NacoWikiApp $wiki, array &$data) : ?bool { }
    //~ static function preSave(\NacoWikiApp $wiki, array &$data) : ?bool { }
    //~ static function save(\NacoWikiApp $wiki, array &$data) : ?bool { }
    //~ static function postSave(\NacoWikiApp $wiki, array &$data) : ?bool { }

    if (!is_array($ext)) $ext = [ $ext ];
    $mlst = [];
    if (is_array($handler)) {
      foreach ($media_handlers as $meth) {
	if (isset($handler[$meth])) {
	  if (is_callable($handler[$meth])) {
	    $mlst[$meth] = $handler[$meth];
	  } elseif (is_callable([$class, $handler[$meth]])) {
	    $mlst[$met] = [$class,$handler[$meth]];
	  }
	}
      }
    } else {
      if (is_null($handler)) $handler = '';
      foreach ($media_handlers as $meth) {
	if (!is_callable([$class, $handler.$meth])) continue;
	$mlst[$meth] = [$class,$handler.$meth];
      }
    }

    foreach ($ext as $media) {
      $media = strtolower($media); // Make sure it is always lowercase
      self::$mediatypes[$media] = $ext[0];
    }

    foreach ($mlst as $event => $callback) {
      self::registerEvent($event.':'.$ext[0],$callback);
    }
  }
  static function mediaExt(string $page) : ?string {
    $ext = strtolower(pathinfo($page)['extension'] ?? '');
    if (isset(self::$mediatypes[$ext])) return self::$mediatypes[$ext];
    return NULL;
  }
}

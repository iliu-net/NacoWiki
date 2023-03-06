<?php
/**  @package NWiki */
namespace NWiki;

/** Plugin management
 *
 * This class managed Plugins, and also includes plugin utility methods.
 */
class PluginCollection {
  /** Event handler return code indicating that event was handled.
   * @var ?bool */
  const OK = false;
  /** Event handler return code indicating that event was handled and event
   * processing should be stopped.
   * @var ?bool */
  const OKSTOP = true;
  /** Event handler return code indicating that event was **NOT** handled.
   * @var ?bool */
  const NOOP = NULL;
  /** API Event handler status code indicating succesful execution
   * @var string */
  const API_OK = 'ok';
  /** API Event handler status code indicating execution failure
   * @var string */
  const API_ERROR = 'error';
  /** List of event hanlders */
  static array $handlers = [];
  /** Used to quickly identified registered handlers */
  static $mediatypes = [];
  /** List of loaded plugins */
  static $plugins = [];

  /** translates configured plugin path into list of directories
   *
   * Given the NacoWiki configuration, it will return
   * an array containing directories where to look for plugins.
   *
   * The default is `APP_DIR/plugins`.
   *
   * @param array $cfg Configuration array
   * @return array List of plugin directories
   */
  static function pluginPath(array $cfg) : array {
    $path = $cfg['plugins_path'] ?? APP_DIR . 'plugins';
    if (!is_array($path)) $path = explode(PATH_SEPARATOR, $path);
    return $path;
  }
  /** Return the file path to the a Plugin source
   *
   * This is a `convenience` function used to determine the folder
   * where a Plugin stores files.
   *
   * It will first look if there is a folder with the same name as
   * the source without the `.php` extension.  If that failes,
   * it will use the dirname of the source php file.
   *
   * @param string $file File to look up.  If not specified it will
   * 			simply return the plugin directory path
   * @return string
   */
  static function path(string $file = NULL) : ?string {
    $trace = debug_backtrace();
    $src = $trace[0]['file'];
    if (strtolower(substr($src,-4)) == '.php') $src = substr($src,0,-4);
    if (!is_dir($src)) $src = dirname($trace[0]['file']);
    $src .= '/';
    if ($file) $src .= ltrim($file,'/');
    return $src;
  }
  /** sets up a API handler as returning an error.
   *
   * To be used by an API handler when detecting an error.  Example call:
   *
   * ```
   * return Plugins::apiError($event, 'Example error');
   * ```
   *
   * # $opts
   *
   * - rc `?bool` - return code, Plugins::OK, Plugins::OKSTOP, Plugins::NOOP.
   * - status `string` - Status to set the event too.  Defaults to Plugins::API_ERROR.
   *
   * @param array $ev Event array
   * @param string $msg Error message
   * @param array $opts Options
   * @return ?bool Returns self::OK by default.  Otherwise the value of $opts[rc]
   */
  static function apiError(array &$ev, string $msg, array $opts = [ 'status' => self::API_ERROR, 'rc' => self::OK ]) : ?bool {
    $ev['status'] = $opts['status'];
    $ev['msg'] = $msg;
    return $opts['rc'];
  }
  /** Load plugins in plugin paths
   *
   * It will use the $cfg[plugins][enabled] and $cfg[plugins][disabled]
   * list to enable or disable the listed pluigns.
   *
   * - The default is to load all plugins.
   * - If a disable list is provided it is used as a blacklist to stop
   *   the given plugins to be loaded.
   * - If a enable list, only the plugins in the list are enabled.
   * - If both enable and disable are given, it first will use the enable list, then
   *   use the disable list.
   *
   * Both for enable and disable lists, it is taken either as an array
   * listing plugin names, or a string containing comma separated
   * plugin names.
   *
   * In the plugin loading process, the `loadPlugin` method will use
   * `require_once` to load the plugin file.  Then it will call the
   * `load` method for all the classes that were created by the
   * plugin file, if they exist.
   *
   * The plugin class then can use the PluginCollection functions to
   * register events or media handlers.  Also, the PluginCollection
   * autoload method can be used.
   *
   * @param array $cfg Wiki configuration
   * @todo Perhaps instead of `require_once`, it should `include_once`.
   */
  static function loadPlugins(array $cfg) : void {
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
  }
  /** Autoload event handlers
   *
   * It will look in the doc strings of the methods for the given class
   * looking for a tag:
   *
   * ```
   * @event _event-name_
   * ```
   *
   * When found, the method will be registerd as the given event
   * handler.
   *
   * @param string $class Class name to autoload
   */
  static function autoload(string $class) : void {
    // automatically register events
    $rc = new \ReflectionClass($class);
    foreach ($rc->getMethods(\ReflectionMethod::IS_STATIC) as $sm) {
      $docstr = $sm->getDocComment();
      if (preg_match('/^\s*\*?\s@event\s*(\S+)/m',$docstr,$mv)) {
	self::registerEvent($mv[1],[$class,$sm->name]);
      }
    }
  }
  /** Utility function to send read-only events
   *
   * When calling dispatchEvent, you can use this to generate an
   * event array that only contains read-only data.
   *
   * Example usage:
   *
   * ```
   * Plugins::dispatchEvent($wiki, 'test-event', Plugins:devt([ 'option1' => 1, 'option2' => 2 ]));
   * @param array $content array containing data that will be placed
   * 			on the newly created event.
   * @return array reference to the event.
   */
  static function &devt(array $content = []) : array {
    $c = $content;
    return $c;
  }
  /** Register an event handler
   */
  static function registerEvent(string $event, $callable) : void {
    if (!isset(self::$handlers[$event])) self::$handlers[$event] = [];
    self::$handlers[$event][] = $callable;
  }
  /** Dispatch events
   *
   * It will call the hooks for the given $event.  Data to the hook
   * is passed through the $data array.  When calling `dispatchEvent`
   * if no data needs to be received, you can leave this argument
   * empty.  Otherwise you need to pass an array preloaded with
   * any input data.  The results will be received throug the
   * same array.
   *
   * For read-only events you can use PluginCollection::devt static
   * function to pass read-only arguments.
   *
   * @param \NacoWikiApp $wiki current wiki instance
   * @param string $event event name that we are dispatching
   * @param array &$data  nullable data for given event
   * @return bool `true` if event was handled, otherwise `false`.
   */
  static function dispatchEvent(\NacoWikiApp $wiki, string $event, array &$data = NULL) : bool {
    // RETURNS
    // true: handled, false: not-handled
    if (is_null($data)) $data = [];

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
  /** Register a new media type
   *
   * Register the $class as a media type handler.
   *
   * It will define the given extension (or extensions) as a media type
   * and will add these methods as event hooks:
   *
   * - view => view:[ext]
   *   - read => read:[ext]
   *   - render => render:[ext]
   *   - layout => layout:[ext]
   * - missing => missing:[ext]
   * - edit => edit:[ext]
   * - save => save:[ext]
   * - preSave => preSave:[ext]
   * - postSave => postSave:[ext]
   *
   * Not all methods are needed, media handlers only need to provide
   * the methods needed, otherwise NacoWiki will provide a suitable
   * default.  Note that if a view method is provided, it will
   * be called first, bypassing read, render and layout.
   *
   * Methods need to be defined with these prototypes:
   *
   * ```php
   * static function view(\NacoWikiApp $wiki, array &$data) : ?bool;
   * static function read(\NacoWikiApp $wiki, array &$data) : ?bool;
   * static function render(\NacoWikiApp $wiki, array &$data) : ?bool;
   * static function layout(\NacoWikiApp $wiki, array &$data) : ?bool;
   * static function missing(\NacoWikiApp $wiki, array &$data) : ?bool;
   * static function edit(\NacoWikiApp $wiki, array &$data) : ?bool;
   * static function preSave(\NacoWikiApp $wiki, array &$data) : ?bool;
   * static function save(\NacoWikiApp $wiki, array &$data) : ?bool;
   * static function postSave(\NacoWikiApp $wiki, array &$data) : ?bool;
   * ```
   *
   * @param string|array $ext -- file extension or array listing file extensions
   * @param string $class -- class name handling this media type
   * @param NULL|string|array $handler -- how media types are handled
   */
  static function registerMedia($ext,string $class, $handler = NULL) : void {
    $media_handlers = [ 'view', 'read', 'render', 'layout', 'missing', 'edit', 'save', 'preSave', 'postSave' ];


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
  /** Check the media handler for the page
   * @param string $page page to look-up the media handler for
   * @return ?string normalized file extension or NULL
   */
  static function mediaExt(string $page) : ?string {
    $ext = strtolower(pathinfo($page)['extension'] ?? '');
    if (isset(self::$mediatypes[$ext])) return self::$mediatypes[$ext];
    return NULL;
  }
}



<?php
/** Canonical name of the NacoWiki application
 * @var string */
define('APP_NAME','NacoWiki');
/** URL of the NacoWiki application
 * @var string */
define('APP_URL', 'https://github.com/iliu-net/NacoWiki/');
/** NacoWiki Application version
 * @var string */
define('APP_VERSION', trim(file_get_contents(dirname(realpath(__FILE__)).'/VERSION')));
/** Current application code directory.
 * @var string */
define('APP_DIR', dirname(realpath(__FILE__)).'/');
/** Used by NacoWikiApp::errMsg to indicate that no special processing is needed
 *  @var int */
define('EM_NONE', 0);
/** Used by NacoWikiApp::errMsg to indicate that PHP Error needs to be displayed
 * @var int */
define('EM_PHPERR', 1);

require(APP_DIR . 'classes/PluginCollection.php');
require(APP_DIR . 'classes/Util.php');
require(APP_DIR . 'classes/Core.php');
require(APP_DIR . 'classes/Cli.php');
require(APP_DIR . 'compat/main.php');

use NWiki\PluginCollection as Plugins;
use NWiki\Util as Util;
use NWiki\Core as Core;
use NWiki\Cli as Cli;

/**
 * Main Application class
 */
class NacoWikiApp {
  /** Contains running app configuration */
  public array $cfg = [
    'umask'		=> NULL,	// optional umask
    'proxy-ips'		=> NULL,	// list of IP addresses of trusted reverse proxies
    'no_cache'		=> false,	// disable browser caches
    'plugins_path'	=> NULL,	// array of path variable where to find plugins
    'base_url'		=> NULL,	// Base Application URL
    'static_url'	=> NULL,	// URL Path to static resources
    'static_path'	=> NULL,	// File Path to static resources
    'file_store'	=> NULL,	// Location where files are stored
    'read_only'		=> false,	// Make the wiki read-only
    'unix_eol'		=> true,	// Convert payload EOL to UNIX style format
    'default_doc'	=> 'index.md',
    'plugins'		=> [],		// Plugin configuration
    // enabled => 'x,y,z',
    // disabled => 'a,b,c',

    'cookie_id'		=> NULL,
    'cookie_age'	=> 86400 * 30,
    'ext_url'		=> '/',		// External URL
    'title'		=> 'NacoWiki',	// Window title bar
    'copyright'		=> 'nobody@nowhere',	// Shown in footer
    'theme'		=> 'nacowiki',	// selected theme
    'theme-highlight-js' => NULL,	// highlight.js theme
    'theme-codemirror'	=> NULL,	// code mirror theme
  ];

  /**
   * List loaded plugins
   * @deprecated Doesn't seem to be used anywhere.
   */
  public array $plugins = [];		// List of loaded plugins
  /** Current document */
  public string $page = '';
  /** Current document meta-data */
  public array $meta = [];
  /** HTTP context, should be set to http or https, otherwise is NULL */
  public ?string $scheme = NULL;
  /** HTTP client's remote address */
  public ?string $remote_addr = NULL;
  /** HTTP request host */
  public ?string $http_host = NULL;
  /** TRUE if using https */
  public bool $https = false;
  /** Current user/session context */
  public array $context = [];
  /** Defined context variables */
  public array $ctxvars = [];
  /**  Default view class */
  public string $view = 'default';

  public function __construct(array $config = []) {
    // Configure Wiki instance
    $this->cfg = array_merge($this->cfg, $config);
    if (!is_null($this->cfg['umask'])) umask($this->cfg['umask']);
    if (is_null($this->cfg['cookie_id'])) $this->cfg['cookie_id'] = 'naco_wiki_' .sprintf('%x',crc32(getcwd()));

    if (isset($_SERVER['REQUEST_SCHEME'])) $this->scheme = $_SERVER['REQUEST_SCHEME'];
    if (isset($_SERVER['REMOTE_ADDR'])) $this->remote_addr = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER['HTTP_HOST'])) $this->http_host = $_SERVER['HTTP_HOST'];

    if ($this->remote_addr && !empty($this->cfg['proxy-ips'])) {
      // Handle reverse proxy environments
      $rp = $this->cfg['proxy-ips'];
      if (!is_array($rp)) $rp = preg_split('/\s*,\s*/',trim($rp));
      if (in_array($this->remote_addr,$rp)) {
	// IP is a registered reverse proxy....
	if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) $this->scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
	if (isset($_SERVER['HTTP_X_REAL_IP'])) $this->remote_addr = $_SERVER['HTTP_X_REAL_IP'];
	if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) $this->http_host = $_SERVER['HTTP_X_FORWARDED_HOST'];
      }
      unset($rp);
    }
    if ($this->scheme == 'https') $this->https = true;

    if (empty($this->cfg['base_url'])) $this->cfg['base_url'] = $_SERVER['SCRIPT_NAME'];
    if (empty($this->cfg['static_url'])) $this->cfg['static_url'] = dirname($_SERVER['SCRIPT_NAME']).'/assets';
    $this->cfg['static_url'] = rtrim($this->cfg['static_url'],'/').'/';
    if (empty($this->cfg['static_path'])) $this->cfg['static_path'] = __DIR__.'/assets';
    $this->cfg['static_path'] = rtrim($this->cfg['static_path'],'/').'/';

    if (empty($this->cfg['file_store'])) $this->cfg['file_store'] = getcwd() . '/files';
    $this->cfg['file_store'] = rtrim($this->cfg['file_store'],'/');
    if (!is_dir($this->cfg['file_store'])) $this->errMsg('config',$this->cfg['file_store'].': Missing file_store configuration');

    // Load plugins
    $this->plugins = Plugins::loadPlugins($this->cfg);

    // Standard event handlers...
    Core::load();

    // CLI event handlers
    Cli::load();
  }
  /**
   * Convert URL to an actual file path
   *
   * @param ?string $url URL to translate, If not given, it would use $this->page
   * @return string actual file path to $url in the filesystem.
   */
  public function filePath(string $url = NULL) : string {
    if (is_null($url)) $url = $this->page;
    return $this->cfg['file_store'] . $url;
  }

  /**
   * Show a HTML error message and dies.
   *
   * Shows an HTML error message and exits.  You can pass optional
   * flags:
   *
   * * EM_NONE: default, no special processing
   * * EM_PHPERR: Display the last PHP Error using php's error_get_last
   *
   * @todo Determine if running as CLI and only display text not HTML.
   *
   * @param string $tag a tag used to identify the error.
   * @param string $msg Error message to display.
   * @param int $flags Flags with options.  Defaults to EM_NONE
   */
  public function errMsg(string $tag, string $msg, int $flags = EM_NONE) : void {
    file_put_contents( "php://stderr",$msg.PHP_EOL); // Write error to logs
    //~ if (Plugins::dispatchEvent($wiki, 'error_msg', Plugins::event([$msg,$flags]))) exit();
    $wiki = $this;
    $this->view = 'error';
    $this->meta = ['title' => 'Fatal Error'];
    include(APP_DIR . 'views/err_msg.html');
    exit;
  }

  /** Current document is writable
   * @todo Currently is only a stub function
   * @param ?string $url URL to translate, If not given, it would use $this->page
   * @return ?bool returns `true` if allowed, `false` if not. `NULL` if user is not authenticated.
   */
  public function isWritable(string $url = NULL) : ?bool {
    $fpath = $this->filePath($url);
    if (empty($this->cfg['read_only'])) {
      $wr = true;
    } else {
      if (!is_bool($this->cfg['read_only']) && $this->cfg['read_only'] == 'not-auth') {
	# TODO: check how auth user is received
	# $_SERVER['PHP_AUTH_USER'] or REMOTE_USER
	$wr = true;
      } else {
	$wr = !filter_var($this->cfg['read_only'],FILTER_VALIDATE_BOOLEAN);
      }
    }
    $event  = [ $fpath, $wr ];
    Plugins::dispatchEvent($this, 'check_writable', $event);
    list(, $wr) = $event;
    return $wr;
  }

  /** Current document is readable
   * @todo Currently is only a stub function
   * @param ?string $url URL to translate, If not given, it would use $this->page
   * @return ?bool returns `true` if allowed, `false` if not. `NULL` if user is not authenticated.
   */
  public function isReadable($url = NULL) : ?bool {
    $event = [ $this->filePath($url), true ];
    Plugins::dispatchEvent($this, 'check_readable', $event);
    list(, $readable) = $event;
    return $readable;
  }

  /** Create a URL to the given resource.
   *
   * Given the $base and $params, it would compute a URL to the given
   * resource.
   *
   * $params is an array of strings or arrays.  If a string is
   * passed in $params, it will simply append it to the
   * URL as another path component.  If an array is passed,
   * it will use it to create a HTTP query string using PHP
   * http_build_query.
   *
   * @param string $base URL to compute
   * @param mixed $params varargs with additional arguments
   * @return string URL to the given resource.
   */
  public function mkUrl(string $base,... $params) : string {
    $path = $base;
    $qstr = [];
    foreach ($params as $param) {
      if (is_array($param)) {
	$qstr = array_merge($qstr,$param);
      } else {
	$path = rtrim($base,'/').'/'.ltrim($param,'/');
      }
    }
    if (count($qstr) > 0) $path .= '?' . http_build_query($qstr);
    return $this->cfg['base_url'].$path;
  }

  /** Returns a URL to an static file asset
   *
   * This returns a string to a static file asset.  The URL is supposed
   * to be used so that the Web server can send the asset directly
   * to the user (by-passing PHP).
   *
   * @param string $id asset identifier
   * @return string URL to the asset
   */
  public function asset(string $id) : string {
    return $this->cfg['static_url'] . $id;
  }
  /** Returns a URL to an static file asset with timestamp
   *
   * This returns a string to a static file asset.  The URL is supposed
   * to be used so that the Web server can send the asset directly
   * to the user (by-passing PHP).
   *
   * This works just like the `asset` method, with the difference that
   * the URL also has a query string of the form of '?t=number`.
   * The number is the mtime of the asset as returned by PHP's filemtime.
   *
   * The purpose is so that the URL send to the browser is different every
   * time the file changes.  This is useful for Web browsers that tend to
   * aggresively cache JavaScript code.
   *
   * @param string $id asset identifier
   * @return string URL to the asset
   */
  public function assetQS(string $id) : string {
    $qs = '';
    if (file_exists($this->cfg['static_path'].$id)) $qs = '?t='.filemtime( $this->cfg['static_path'].$id );
    return $this->cfg['static_url'] . $id . $qs;
  }
  /**
   * Main dispatch point for CLI sub-commands
   *
   * It will arrange for the given sub-command to be executed.  This is
   * done by firing the event `cli:SUBCOMMAND`.  Where `SUBCOMMAND` is the
   * sub-command specified in the command line.
   *
   * The current instance will also get the following properties defined:
   *
   * - $this->script : $argv[0], usually the script name
   * - $this->cwd : Working directory form the command was invoked
   * - $this->script_dir : Directory of the script
   * - $this->clicmd : sub command being executed.
   *
   * @todo Should do a `chdir` to the $this->script_dir to be more similar to a running web environment?
   *
   * @paran array $argv Command line arguments
   * @return void
   */
  public function cli(array $argv) : void {
    // CLI entry point
    if (count($argv) < 2) {
      fwrite(STDERR, 'Usage: '.PHP_EOL.'   '. $argv[0]. ' cmd [options]'.PHP_EOL);
      exit(15);
    }

    $this->script = array_shift($argv);
    $this->cwd = getcwd();
    $this->script_dir = dirname(realpath($this->script));
    $this->clicmd = array_shift($argv);

    if (!Plugins::dispatchEvent($this, 'cli:'.$this->clicmd, $argv)) {
      fwrite(STDERR,$this->clicmd.': Unknown sub command'.PHP_EOL);
      exit(16);
    }
  }

  /**
   * Main application entry point method
   *
   * @param ?array $argv  Normally NULL. Contains command line arguments if run from CLI
   */
  public function run(array $argv = NULL) : void {
    // pre-run init
    $this->declareContext('debug',NULL);
    Plugins::dispatchEvent($this, 'run_init', Plugins::event());

    if ($argv && is_array($argv)) {
      $this->cli($argv);
      exit;
    }
    if ($this->cfg['no_cache']) {
      header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
      header("Cache-Control: post-check=0, pre-check=0", false);
      header("Pragma: no-cache");
    }

    // Check run context
    $this->initContext();
    //~ $this->checkContext();
    Plugins::dispatchEvent($this, 'context_loaded', Plugins::event());

    if (!empty($_SERVER['PATH_INFO'])) $url = $_SERVER['PATH_INFO'];
    if (!empty($url) && !empty($_GET['url'])) $url = trim($_GET['url'],'/');

    // Handle HTTP request path
    if (isset($url)) {
      $url = Util::sanitize($url);
      if (!empty($url)) {
	$this->page = $url;
	//~ Plugins::dispatchEvent($this, 'url_init', $this->page);
      }
    }
    //~ echo('TRC:'.__FILE__.','.__LINE__.':page:'. $this->page);
    if (!empty($_GET['go'])) {
      $go = Util::sanitize($_GET['go'],$this->page);
      header('Location: '.$this->mkUrl($go));
      exit;
    }

    // Main action dispatcher
    if (!empty($_GET['do'])) {
      if (!Plugins::dispatchEvent($this, 'do:'.strtolower($_GET['do']), Plugins::event())) {
	$this->errMsg('invalid_do',$_GET['do'].': Invalid command');
      }
    } elseif (!empty($_GET['api'])) {
      header('Content-type: application/json');
      $api_ev = [ 'status' => Plugins::API_OK ]; // Assume success
      if (!Plugins::dispatchEvent($this, 'api:'.strtolower($_GET['api']), $api_ev)) {
	Plugins::apiError($api_ev,$_GET['api'].': Invalid endpoint');
	die(json_encode($api_ev));
      }
      echo json_encode($api_ev);
      exit;
    } elseif (count($_POST)) {
      if (!$this->isWritable()) {
	$this->errMsg('write_access',$this->filePath().': No write access');
      }
      if (empty($_POST['action'])) $this->errMsg("No action in POST");
      if (!Plugins::dispatchEvent($this, 'action:'.strtolower($_POST['action']), Plugins::event())) {
	$this->errMsg('invalid_action',$_POST['action'].': Invalid action');
      }
    } else {
      if (is_dir($this->filePath())) {
	if (!$this->isReadable()) {
	  $this->errMsg('read_access', $this->filePath().': Access denied');
	}
	Plugins::dispatchEvent($this, 'read_folder', Plugins::event());
      } elseif (file_exists($this->filePath())) {
	if (!$this->isReadable()) {
	  $this->errMsg('read_access', $this->filePath().': Access denied');
	}
	Plugins::dispatchEvent($this, 'read_page', Plugins::event());
      } else {
	Plugins::dispatchEvent($this, 'missing_page', Plugins::event());
      }
    }
  }
  /**
   * Declare a context variable.
   *
   * Declare context variables that persist in an user session.
   * This is implemented using Cookies.
   *
   * Plugins should hook into the `run-init` event and declare
   * context variables there.  Value of these variables can
   * be read from the $wiki->context[$key]
   *
   * @param string $key variable name to declare
   * @param mixed $default default value.
   */
  public function declareContext(string $key,$default=NULL) : void {
    $this->ctxvars[$key] = $default;
  }
  /**
   * Initialize user context
   *
   * It would initialize the class `context` property from the
   * http cookies.  Also it would allow the user to change the
   * context through $_GET variables.  Specifically, you can
   * use:
   *
   * ...url...?ctx_VARNAME=value
   *
   * to set the context variable VARNAME to the given value.
   *
   * Similarly, you can use:
   *
   * ...url...?noctx_VARNAME=null
   *
   * To set VARNAME to its default value.
   *
   * Example:
   *
   * ctx_debug=true
   *
   * This enables the debug context variable.
   *
   * @todo Add function to modify context afterwards (maybe using header_remove(set-cookie))
   */
  public function initContext() : void {
    $cookie_name = $this->cfg['cookie_id'].'_context';
    if (isset($_COOKIE[$cookie_name])) {
      parse_str($_COOKIE[$cookie_name],$context);
    } else {
      $context = [];
    }
    $set_cookie = false;

    if (empty($context['timestamp'])) $context['timestamp'] = 1;

    foreach ($this->ctxvars as $k=>$v) {
      if (empty($context[$k])) $context[$k] = $v;
      if (!empty($_GET['ctx_'.$k])) {
	$m = $_GET['ctx_'.$k];
	if (is_string($context[$k]) && strtolower($m) == strtolower($context[$k])) continue;
	$context[$k] = $m;
	$set_cookie = true;
      } elseif (!empty($_GET['noctx_'.$k])) {
	if ($context[$k] === $this->ctxvars[$k]) continue;
	$context[$k] = $this->ctxvars[$k];
	$set_cookie = true;
      }
    }

    if ($set_cookie || time()-$context['timestamp'] > $this->cfg['cookie_age']/2) {
      $context['timestamp'] = time();
      setcookie($cookie_name,http_build_query($context), [
	'expires' => time() + $this->cfg['cookie_age'],
	'path' => dirname($_SERVER['SCRIPT_NAME']),
	'secure' => $this->https,
	'httponly' => true,
	'samesite' => 'Lax',
      ]);
    }
    $this->context = $context;
  }
}


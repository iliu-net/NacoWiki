<?php
define('APP_NAME','NacoWiki');
define('APP_URL', 'https://github.com/iliu-net/NacoWiki/');
define('APP_VERSION', trim(file_get_contents(dirname(realpath(__FILE__)).'/VERSION')));
define('APP_DIR', dirname(realpath(__FILE__)).'/');

define('EM_NONE', 0);
define('EM_PHPERR', 1);

require(APP_DIR . 'classes/PluginCollection.php');
require(APP_DIR . 'classes/Util.php');
require(APP_DIR . 'classes/Core.php');
require(APP_DIR . 'classes/Cli.php');
use NWiki\PluginCollection as Plugins;
use NWiki\Util as Util;
use NWiki\Core as Core;
use NWiki\Cli as Cli;

if (!function_exists('yaml_emit')) {
  //
  // For installations where yaml is not compiled in...
  // we use: https://github.com/eriknyk/Yaml
  require(APP_DIR.'compat/Yaml.php');
  function yaml_emit($data) {
    $yaml = new Alchemy\Component\Yaml\Yaml();
    return $yaml->dump($data);
  }
  function yaml_parse($doc) {
    $yaml = new Alchemy\Component\Yaml\Yaml();
    return $yaml->loadString($doc);
  }
}

class NacoWikiApp {
  public $cfg = [
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

  public $plugins = [];		// List of loaded plugins
  public $page = '';		// Current document
  public $meta = [];		// Current document meta-data
  // HTTP context
  public $scheme = NULL;
  public $remote_addr = NULL;
  public $http_host = NULL;
  public $https = false;
  // User context
  public $context = [];		// Current user/session context
  public $ctxvars = [];		// Defined context variables
  public $view = 'default';	// Default view class

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
  public function filePath(string $url = NULL) : string {
    if (is_null($url)) $url = $this->page;
    return $this->cfg['file_store'] . $url;
  }

  public function errMsg(string $tag, string $msg, int $flags = EM_NONE) : void {
    file_put_contents( "php://stderr",$msg.PHP_EOL); // Write error to logs
    //~ if (Plugins::dispatchEvent($wiki, 'error_msg', Plugins::event([$msg,$flags]))) exit();
    $wiki = $this;
    $this->view = 'error';
    $this->meta = ['title' => 'Fatal Error'];
    include(APP_DIR . 'views/err_msg.html');
    exit;
  }

  /* These two functions are stubs for the moment... */
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

  public function isReadable($url = NULL) : ?bool {
    $event = [ $this->filePath($url), true ];
    Plugins::dispatchEvent($this, 'check_readable', $event);
    list(, $readable) = $event;
    return $readable;
  }

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
  public function asset(string $id) : string {
    return $this->cfg['static_url'] . $id;
  }
  public function assetQS(string $id) : string {
    $qs = '';
    if (file_exists($this->cfg['static_path'].$id)) $qs = '?t='.filemtime( $this->cfg['static_path'].$id );
    return $this->cfg['static_url'] . $id . $qs;
  }

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

  public function run($argv = NULL) : void {
    if ($argv && is_array($argv)) {
      $this->cli($argv);
      exit;
    }
    if ($this->cfg['no_cache']) {
      header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
      header("Cache-Control: post-check=0, pre-check=0", false);
      header("Pragma: no-cache");
    }

    // pre-run init
    $this->declareContext('debug',NULL);
    Plugins::dispatchEvent($this, 'run_init', Plugins::event());

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
      if (!Plugins::dispatchEvent($this, 'api:'.strtolower($_GET['api']), Plugins::event())) {
	die(json_encode([
	  'status' => 'error',
	  'msg' => $_GET['api'].': Invalid command',
	]));
      }
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
  public function declareContext($key,$default=NULL) {
    $this->ctxvars[$key] = $default;
  }
  public function initContext() {
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


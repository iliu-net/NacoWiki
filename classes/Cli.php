<?php
namespace NWiki;
use NWiki\PluginCollection as Plugins;

class Cli {
  const TRC = 1;
  const NONL = 2;
  static function relativePath($from, $to, $ps = DIRECTORY_SEPARATOR) {
    $arFrom = explode($ps, rtrim($from, $ps));
    $arTo = explode($ps, rtrim($to, $ps));
    while(count($arFrom) && count($arTo) && ($arFrom[0] == $arTo[0]))
    {
      array_shift($arFrom);
      array_shift($arTo);
    }
    return str_pad("", count($arFrom) * 3, '..'.$ps).implode($ps, $arTo);
  }
  static function stderr(string $msg, int $flags = 0) : void {
    $tag = '';
    if (self::TRC & $flags) {
      $trace = debug_backtrace();
      $file = $trace[0]['file'];
      if (defined('APP_DIR')) {
	if (substr($file,0,strlen(APP_DIR)) == APP_DIR) {
	  $file = substr($file,strlen(APP_DIR));
	}
      }

      $tag = $file.','.$trace[0]['line'].':';
      if (isset($trace[1])) {
	foreach (['class','type','function'] as $k) {
	  if (!empty($trace[1][$k])) $tag .= $trace[1][$k];
	}
      }
      $tag .= ': ';
    }
    file_put_contents( "php://stderr",$tag.$msg);
    if (self::NONL & $flags == 0) echo PHP_EOL;
  }

  /**
   * Show this help
   */
  static function help(\NacoWikiApp $wiki, array $argv) : ?bool {
    echo('Available sub-commands'.PHP_EOL);
    foreach (Plugins::$handlers as $i=>$j) {
      if (preg_match('/^cli:(.*)$/', $i, $mv)) {
	$cmd = $mv[1];
	$rr = new \ReflectionMethod(... $j[0]);
	$docstr = $rr->getDocComment();
	if ($docstr) {
	  if (substr($docstr,0,3) == '/**') $docstr = ltrim(substr($docstr,3));
	  if (substr($docstr,0,1) == '*') $docstr = ltrim(substr($docstr,1));
	  if (substr($docstr,-2) == '*/') $docstr = rtrim(substr($docstr,0,-2));
	  if (($i = strpos($docstr,"\n")) !== false) $docstr = rtrim(substr($docstr,0,$i));
	  $docstr = ': '.$docstr;
	}
	echo '- '. $cmd . $docstr.' ('.$j[0][0].')'.PHP_EOL;
      }
    }
    exit;
  }
  /**
   * Dump plugin configuraiton
   */
  static function plugins(\NacoWikiApp $wiki, array $argv) : ?bool {
    foreach (Plugins::$plugins as $x=>$y) {
      $v = @constant($x.'::VERSION') ?? 'n/a';
      echo '- '.$x.' ('.$v.') : '.self::relativePath(getcwd(),$y).PHP_EOL;
    }
    //~ var_dump(Plugins::$handlers);
    //~ print_r(Plugins::$mediatypes);
    //~ var_dump(Plugins::$stack);
    //~ print_r(Plugins::$plugins);
    exit;
  }
  /**
   * try things
   */
  static function ts(\NacoWikiApp $wiki, array $argv) : ?bool {
    //~ Plugins::path();
    Plugins::dispatchEvent($wiki,'layout:html',$argv);
    exit;
  }

  /**
   * Install assets
   */
  static function install(\NacoWikiApp $wiki, array $argv) : ?bool {
    if (count($argv) != 0) die('Too many arguments'.PHP_EOL);

    $assets = $wiki->cfg['static_path'];

    if (!is_dir($assets) && false === mkdir($assets)) die($assets. ': mkdir error'.PHP_EOL);

    // Link main app static assets
    foreach (glob(APP_DIR . 'assets/*') as $src) {
      $rpath = self::relativePath($assets,$src);
      $dst = $assets . basename($src);
      if (file_exists($dst)) continue;
      if (false === symlink($rpath, $dst)) die($dst. ': symlink failed'.PHP_EOL);
    }

    // Link plugin assets
    foreach (Plugins::pluginPath($wiki->cfg) as $plugdir) {
      $plugs = glob($plugdir . '/*/assets',GLOB_ONLYDIR);
      if ($plugs === false) continue;

      foreach ($plugs as $pldir) {
	$plname = basename(dirname($pldir));
	$link = $assets . 'plugins/'.$plname;
	if (!is_dir($assets . 'plugins') && false === mkdir($assets.'plugins')) die($assets.'/plugins: mkdir error'.PHP_EOL);

	$rpath = self::relativePath(dirname($link), $pldir);
	//~ print_r([
	  //~ 'dir' => $pldir,
	  //~ 'name' => $plname,
	  //~ 'link' => $link,
	  //~ 'rpath' => $rpath,
	//~ ]);
	if (file_exists($link)) continue;
	if (false === symlink($rpath, $link)) die($link.': symlink failed'.PHP_EOL);
      }
    }


    exit;
  }

  static function load() : void {
    Plugins::registerEvent('cli:install',[self::class,'install']);
    Plugins::registerEvent('cli:help',[self::class,'help']);
    Plugins::registerEvent('cli:plugins',[self::class,'plugins']);
    Plugins::registerEvent('cli:t',[self::class,'ts']);
  }
}

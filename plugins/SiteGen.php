<?php
/** SiteGen
 *
 * Site generation functionality
 *
 * @package Plugins
 * @phpcod Plugins##Sitegen
 * @todo This is still a work in progress
 */

use NWiki\PluginCollection as Plugins;
use NWiki\Util as Util;
use NWiki\Core as Core;
use NWiki\Cli as Cli;

/** Site generation
 *
 * Class that actually implements SiteGen
 *
 * @phpcod SiteGen
 */
class SiteGen {
  /** var string */
  const VERSION = '0.0';

  /** var string[] */
  static $exts = NULL;

  /** Return this plugin's path
   * @param string $f optional item
   * @return path to filesystem for $f
   */
  static function path(string $f = '') : string {
    return Plugins::path($f);
  }
  /**
   * Fix generated links
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array $argv Command line arguments
   */
  static function postRender(\NacoWikiApp $wiki, array &$ev) : ?bool {
    //~ Cli::stderr($ev['html']);
    if (preg_match_all('/<[Aa]\s+[Hh][Rr][Ee][fF]="([^"]+)"/', $ev['html'], $mv)) {
      $vars = [];
      foreach ($mv[0] as $m=>$lnk) {
	if (!$mv[1][$m]) continue;
	$p = $mv[1][$m];
	if (!str_starts_with($p,$wiki->cfg['base_url'])) continue;
	if (is_null(self::$exts)) {
	  $ext = Plugins::mediaExt($p);
	  if (is_null($ext)) continue;
	} else {
	  $ext = strtolower(pathinfo($p)['extension'] ?? '');
	  if (!isset(self::$exts[$ext])) continue;
	}
	$i = strrpos($p,'.');
	if ($i === false) continue;
	$p = substr($p,0,$i);
	$vars[$lnk] = '<a href="'.$p.'.html"';
	//~ Cli::stderr($p);
      }
      if (count($vars) > 0) $ev['html'] = strtr($ev['html'],$vars);
    }
    return Plugins::OK;
  }

  /**
   * Render a wiki page as HTML
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array $argv Command line arguments
   * @phpcod commands##render
   * @event cli:render
   */
  static function render(\NacoWikiApp $wiki, array $argv) : ?bool {
    $opts = [
      'view' => Plugins::path('page.html')
    ];
    while (count($argv) > 0) {
      if (str_starts_with($argv[0],'--view=')) {
	$opts['view'] = substr($argv[0],strlen('--view-'));
      } elseif (str_starts_with($argv[0],'--cfg-')) {
	$kv = explode('=',substr($argv[0],strlen('--cfg-')),2);
	if (empty($kv[0])) die('Incomplete cfg argument'.PHP_EOL);
	if (count($kv) == 1) $kv[] = true;
	$wiki->cfg[$kv[0]] = $kv[1];
      } else {
	break;
      }
      array_shift($argv);
    }

    if (count($argv) != 1) die('Must specify one page to render'.PHP_EOL);

    Plugins::registerEvent('post-render', [self::class, 'postRender']);

    $fs =$wiki->cfg['file_store'];
    $pgc = $argv[0];

    # Determine if it is a file or a webpage reference
    if (file_exists($pgc)) {
      $pgc = realpath($pgc);
      if (substr($pgc,0,strlen($fs)+1) != $fs.'/')
	die($argv[0].': falls outside the filestore'.PHP_EOL);
      $pgc = substr($pgc,strlen($fs));
    } else {
      # Try to see if it an article path
      $pgc = '/'. trim($pgc,'/');
      if (!file_exists($fs.$pgc)) die($argv[0].': not found'.PHP_EOL);
    }
    $wiki->page = $pgc;
    //~ print_r([
      //~ 'file_store' => $wiki->cfg['file_store'],
      //~ 'page' => $pgc,
    //~ ]);
    Plugins::dispatchEvent($wiki, 'read_page', $opts);
    exit;
  }

  /** Copy assets
   *
   *  Makes a copy of static assets to the generated site
   *
   * @phpcod commands##mkassets
   * @event cli:mkassets
   */
  static function mkassets(\NacoWikiApp $wiki, array $argv) : ?bool {
    $outdir = NULL;
    while (count($argv) > 0) {
      if (str_starts_with($argv[0],'--output=')) {
	$outdir = substr($argv[0],strlen('--output='));
      } elseif ($argv[0] == '-O') {
	$outdir = $argv[1];
	array_shift($argv);
      } else {
	break;
      }
      array_shift($argv);
    }
    if (empty($outdir)) die('No output dir specified');
    $outdir = rtrim($outdir,'/').'/';

    list($dirs,$files) = Util::walkTree(self::path('assets'));
    foreach ($dirs as $d) {
      if (is_dir($outdir.$d)) continue;
      if (mkdir($outdir.$d,0777,true) === false) die($d.': error creating directory'.PHP_EOL);
    }
    foreach ($files as $f) {
      if (is_file($outdir.$f)) continue;
      if (copy(self::path('assets/'.$f),$outdir.$f) === false) die($f.': error copying file'.PHP_EOL);
    }

    exit;
  }
  /** List files
   *
   *  Makes a list of Wiki files
   *
   * @phpcod commands##files
   * @event cli:files
   */
  static function lst(\NacoWikiApp $wiki, array $argv) : ?bool {
    list($dirs,$files) = Util::walkTree($wiki->cfg['file_store']);
    print_r($dirs);
    print_r($files);
    exit;
  }
  /**
   * Loading entry point for this class
   *
   * Adds commands implemented by this class
   */
  static function load(array $cfg) : void {
    Plugins::autoload(self::class);
    //~ Plugins::registerEvent('cli:render', [self::class, 'render']);
    //~ Plugins::registerEvent('cli:files', [self::class, 'lst']);
  }
}

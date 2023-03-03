<?php
use NWiki\PluginCollection as Plugins;
use NWiki\Util as Util;
use NWiki\Core as Core;
use NWiki\Cli as Cli;

/**
 * Class that actually implements SiteGen
 *
 * @package Plugins\SiteGen
 */
class SiteGen {
  const VERSION = '0.0';

  /**
   * Render a wiki page as HTML
   */
  static function render(\NacoWikiApp $wiki, array $argv) : ?bool {
    $opts = [
      'view' => Plugins::path('page.html')
    ];
    while (count($argv) > 0) {
      if (substr($argv[0],0,strlen('--view=')) == '--view=') {
	$opts['view'] = substr($argv[0],strlen('--view-'));
      } else {
	break;
      }
      array_shift($argv);
    }
    //~ print_r($opts);

    if (count($argv) != 1) die('Must specify one page to render'.PHP_EOL);

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

  /** List files */
  static function lst(\NacoWikiApp $wiki, array $argv) : ?bool {
    list($dirs,$files) = Util::walkTree($wiki->cfg['file_store']);
    print_r($dirs);
    print_r($files);
    exit;
  }

  static function load(array $cfg) : void {
    Plugins::registerEvent('cli:render', [self::class, 'render']);
    Plugins::registerEvent('cli:files', [self::class, 'lst']);
  }
}

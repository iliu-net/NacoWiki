<?php
/**
 * NacoWiki Vars
 *
 * This plugin is used to render config and meta data on a page
 * @package Plugins\PluginVars
 */
use NWiki\PluginCollection as Plugins;


class PluginVars {
  const VERSION = '2.0.0';

  /** Dump global variables */
  static function dumpVars(\NacoWikiApp $wiki, array $argv) : ?bool {
    $key = basename(__FILE__,'.php');
    if (empty($wiki->cfg['plugins'][$key])) {
      die('No keys defined in [plugins]['.$key.']'.PHP_EOL);
    }
    //~ print_r($wiki->cfg['plugins'][$key]);
    echo yaml_emit($wiki->cfg['plugins'][$key]);
    exit;
  }

  /** Dump config */
  static function dumpCfg(\NacoWikiApp $wiki, array $argv) : ?bool {
    if (empty($wiki->cfg)) die('Configuration error'.PHP_EOL);
    //~ print_r($wiki->cfg);
    echo yaml_emit($wiki->cfg);
    exit;
  }

  static function load(array $cfg) : void {
    Plugins::registerEvent('cli:gvars', [self::class, 'dumpVars']);
    Plugins::registerEvent('cli:cfg', [self::class, 'dumpCfg']);

    Plugins::registerEvent('post-render', function(\NacoWikiApp $wiki, array &$ev) {
      $vars = [];
      # We do it like this because these expansions may be expensive...
      if (strpos($ev['html'],'$plugins$') !== false) {
	$vars['$plugins$'] = '<ul>';
	foreach (Plugins::$plugins as $x=>$y) {
	  $v = eval('return '.$x.'::VERSION;');
	  $vars['$plugins$'] .= '<li>'.$x.' ('.$v.') : '.htmlspecialchars($y).'</li>';
	}
	$vars['$plugins$'] .= '</ul>';
      }
      if (strpos($ev['html'],'$attachments$') !== false) {
	$fpath = $wiki->page;
	$pi = pathinfo($fpath);
	if ($pi['basename'] == $wiki->cfg['default_doc']) {
	  $fpath = $pi['dirname'];
	} else {
	  $fpath = $pi['dirname'].'/'.$pi['filename'];
	}
	$fpath .= '/';
	$lst = [];
	$dp = @opendir($wiki->filePath($fpath));
	if ($dp !== false) {
	  while (false !== ($fn = readdir($dp))) {
	    if ($fn[0] == '.' || $fn == $wiki->cfg['default_doc']) continue;
	    $lst[$fpath.$fn] = $fn;
	  }
	  closedir($dp);
	}
	if (count($lst) == 0) {
	  $vars['$attachmetns$'] = '<p>No attachments</p>';
	} else {
	  natsort($lst);
	  $vars['$attachments$'] = '<ul>';
	  foreach ($lst as $dh => $fn) {
	    $vars['$attachments$'] .= '<li>'.
		  (is_dir($wiki->filePath($dh)) ? '&#x1F4C1;' : '&#x1F5CE;') .
		  ' <a href="'.$wiki->mkUrl($dh).'">'.
		  htmlspecialchars($fn).
		  '</a></li>';
	  }
	  $vars['$attachments$'] .= '</ul>';
	}
      }
      if (count($vars) > 0) $ev['html'] = strtr($ev['html'], $vars);
      return Plugins::OK;
    });

    Plugins::registerEvent('pre-render', function(\NacoWikiApp $wiki, array &$ev) {
      # variable substitutions #
      $vars = [
	'$url$' => $wiki->page,
      ];
      foreach ([
	    '' => $wiki->cfg['plugins'][basename(__FILE__,'.php')] ?? [],
	    'cfg.' => $wiki->cfg ?? [],
	    'meta.'=> $wiki->meta ?? [],
	    'file.' => $wiki->filemeta ?? [],
	    'prop.' => $wiki->props ?? [],
	  ] as $nsp => &$reg) {
	foreach ($reg as $k=>$v) {
	  $vars['$'.$nsp.$k.'$'] = is_array($v) ? yaml_emit($v) : $v;
	}
      }
      $vars['$cfg$'] = yaml_emit($wiki->cfg);
      $vars['$vars$'] = yaml_emit($wiki->cfg['plugins'][basename(__FILE__,'.php')] ?? []);

      $ev['html'] =  strtr($ev['html'],$vars);
      return Plugins::OK;
    });
  }
}
